<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\Report;
use App\Support\FeedCursor;
use App\Support\FeedDiversifier;
use App\Support\FeedScore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Smart feed ranking: time decay + engagement + author diversity.
 *
 *  - recomputeEngagement()  keeps posts.engagement_points / feed_score fresh when something changes.
 *  - rescore()              applies time decay to every post in the ranked window (scheduled).
 *  - pageIds()              returns the ids for one feed page from a frozen "snapshot", so scores that
 *                           drift between "Load more" taps can never produce duplicates or gaps.
 *
 * It returns ids only; CommunityService hydrates them into the arrays the views already use.
 */
class FeedRankingService
{
    public function __construct(private PointService $points)
    {
    }

    /* ---------- reading the feed ---------- */

    /**
     * Posts this viewer may see in the feed (shared by the snapshot, the tail and the hydration query).
     *
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    public function visible(Builder $query, int $viewerId, ?string $category = null): Builder
    {
        return $query
            ->whereNull('posts.removed_at')
            ->whereNotExists(function ($sub) use ($viewerId) {
                $sub->selectRaw('1')
                    ->from('reports')
                    ->whereColumn('reports.reportable_id', 'posts.id')
                    ->where('reports.reportable_type', Post::class)
                    ->where('reports.reporter_id', $viewerId)
                    ->whereIn('reports.status', [Report::OPEN, Report::VOTING]);
            })
            ->when($category !== null, fn ($q) => $q->where('posts.category', $category));
    }

    /**
     * One page of post ids, best first.
     *
     * No cursor (or a cursor whose snapshot expired) = build a fresh snapshot, so new posts show up at once.
     * With a cursor we keep paging the same frozen order. Past the end of the snapshot the feed continues
     * newest-first with everything the snapshot did not contain.
     *
     * @return array{ids: array<int, int>, has_more: bool, next_cursor: ?string}
     */
    public function pageIds(int $viewerId, ?string $category, ?string $rawCursor): array
    {
        $perPage = max(1, (int) config('feed.snapshot.per_page'));
        $cursor = FeedCursor::decode($rawCursor);
        $snapshot = $cursor !== null ? Cache::get($this->cacheKey($viewerId, $cursor['token'])) : null;

        if (! is_array($snapshot) || ! isset($snapshot['ids'], $snapshot['built_at'])) {
            $snapshot = $this->buildSnapshot($viewerId, $category);
            $token = Str::random(16);

            Cache::put(
                $this->cacheKey($viewerId, $token),
                $snapshot,
                now()->addMinutes((int) config('feed.snapshot.ttl_minutes')),
            );
        } else {
            $token = $cursor['token'];
        }

        $offset = $cursor['offset'] ?? 0;
        $all = $snapshot['ids'];
        $count = count($all);

        $ids = array_slice($all, $offset, $perPage);
        $need = $perPage - count($ids);              // rows still to take from the newest-first tail
        $hasMore = ($offset + $perPage) < $count;

        if (! $hasMore) {
            // One extra row tells us whether anything is left after this page.
            $tail = $this->tailIds($viewerId, $category, $snapshot, max(0, $offset - $count), $need + 1);

            $ids = array_merge($ids, array_slice($tail, 0, $need));
            $hasMore = count($tail) > $need;
        }

        return [
            'ids' => $ids,
            'has_more' => $hasMore,
            'next_cursor' => $hasMore ? FeedCursor::encode($token, $offset + $perPage) : null,
        ];
    }

    /** @return array{ids: array<int, int>, built_at: string} */
    private function buildSnapshot(int $viewerId, ?string $category): array
    {
        $rows = $this->visible(Post::query(), $viewerId, $category)
            ->where('posts.created_at', '>=', now()->subDays((int) config('feed.window_days')))
            ->select('posts.id', 'posts.author_id', 'posts.feed_score', 'posts.created_at')
            ->selectRaw(
                'EXISTS (SELECT 1 FROM reports WHERE reports.reportable_id = posts.id AND reports.reportable_type = ? AND reports.status IN (?, ?, ?)) AS under_review',
                [Post::class, Report::OPEN, Report::VOTING, Report::ESCALATED],
            )
            ->orderByDesc('posts.feed_score')
            ->orderByDesc('posts.created_at')
            ->orderByDesc('posts.id')
            ->limit(max(1, (int) config('feed.snapshot.size')))
            ->get();

        $factor = (float) config('feed.reported_factor');

        $ranked = $rows->map(fn (Post $row) => [
            'id' => (int) $row->id,
            'author_id' => (int) $row->author_id,
            'score' => (float) $row->feed_score * ((int) $row->under_review === 1 ? $factor : 1.0),
            'created' => (int) ($row->created_at?->getTimestamp() ?? 0),
        ])->all();

        usort($ranked, fn (array $a, array $b) => [$b['score'], $b['created'], $b['id']] <=> [$a['score'], $a['created'], $a['id']]);

        $spread = FeedDiversifier::spread($ranked, (int) config('feed.diversity_lookback'));

        // The viewer's own fresh posts go first (the "just posted" animation expects it).
        $pinned = Post::query()
            ->where('author_id', $viewerId)
            ->whereNull('removed_at')
            ->where('created_at', '>=', now()->subMinutes((int) config('feed.pin_own_minutes')))
            ->when($category !== null, fn ($q) => $q->where('category', $category))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return [
            'ids' => array_values(array_unique([...$pinned, ...array_column($spread, 'id')])),
            'built_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Everything the snapshot did not contain, newest first. Posts created after the snapshot was
     * built are left out so a new post cannot shift the rows we are paging through.
     *
     * @param  array{ids: array<int, int>, built_at: string}  $snapshot
     * @return array<int, int>
     */
    private function tailIds(int $viewerId, ?string $category, array $snapshot, int $start, int $limit): array
    {
        return $this->visible(Post::query(), $viewerId, $category)
            ->where('posts.created_at', '<=', $snapshot['built_at'])
            ->when($snapshot['ids'] !== [], fn ($q) => $q->whereNotIn('posts.id', $snapshot['ids']))
            ->orderByDesc('posts.created_at')
            ->orderByDesc('posts.id')
            ->offset($start)
            ->limit($limit)
            ->pluck('posts.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function cacheKey(int $viewerId, string $token): string
    {
        return "feed:snapshot:{$viewerId}:{$token}";
    }

    /* ---------- keeping scores fresh ---------- */

    /** Call whenever a like, comment, helpful mark, accepted answer or removal touches this post. */
    public function recomputeEngagement(int $postId): void
    {
        $post = Post::query()->find($postId, ['id', 'author_id', 'category', 'created_at', 'removed_at']);

        if ($post !== null) {
            $this->recomputePost($post);
        }
    }

    public function recomputePost(Post $post): void
    {
        if ($post->removed_at !== null) {
            DB::table('posts')->where('id', $post->id)->update(['engagement_points' => 0, 'feed_score' => 0]);

            return;
        }

        $eligible = $this->points->awardsPoints($post); // helpful + accepted only count on Ask / Help / Academic

        $likes = Reaction::query()
            ->where('reactable_type', Post::class)
            ->where('reactable_id', $post->id)
            ->where('type', Reaction::LIKE)
            ->count();

        $commenters = Comment::query()
            ->where('post_id', $post->id)
            ->whereNull('removed_at')
            ->where('author_id', '!=', $post->author_id)
            ->distinct()
            ->count('author_id');

        $helpful = 0;
        $accepted = false;

        if ($eligible) {
            $helpful = Reaction::query()
                ->where('type', Reaction::HELPFUL)
                ->where('reactable_type', Comment::class)
                ->whereIn('reactable_id', Comment::query()->where('post_id', $post->id)->whereNull('removed_at')->select('id'))
                ->count();

            $accepted = Comment::query()
                ->where('post_id', $post->id)
                ->where('is_accepted', true)
                ->whereNull('removed_at')
                ->exists();
        }

        $engagement = FeedScore::engagementPoints(
            $likes,
            $commenters,
            $helpful,
            $accepted,
            (array) config('feed.weights'),
            (array) config('feed.caps'),
        );

        $insideWindow = $post->created_at >= now()->subDays((int) config('feed.window_days'));
        $hours = max(0, $post->created_at->diffInSeconds(now(), false)) / 3600;

        DB::table('posts')->where('id', $post->id)->update([
            'engagement_points' => $engagement,
            'feed_score' => $insideWindow
                ? FeedScore::score((float) config('feed.base_score'), $engagement, $hours, (float) config('feed.gravity'), (float) config('feed.age_offset_hours'))
                : 0,
        ]);
    }

    /** Recompute engagement for every post in the ranked window. Used for the backfill and a nightly self-heal. */
    public function rebuildEngagement(): int
    {
        $count = 0;

        Post::query()
            ->select('id', 'author_id', 'category', 'created_at', 'removed_at')
            ->whereNull('removed_at')
            ->where('created_at', '>=', now()->subDays((int) config('feed.window_days')))
            ->chunkById(200, function ($posts) use (&$count) {
                foreach ($posts as $post) {
                    $this->recomputePost($post);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Apply time decay to every post in the ranked window with ONE update (no joins: it only reads the
     * stored engagement_points). Same formula as FeedScore::score(). Returns the number of rows changed.
     */
    public function rescore(): int
    {
        $now = now();
        $cutoff = $now->copy()->subDays((int) config('feed.window_days'));
        $base = (float) config('feed.base_score');
        $gravity = (float) config('feed.gravity');
        $offset = (float) config('feed.age_offset_hours');

        // Outside the window, or removed: no score, so they only ever appear in the newest-first tail.
        DB::table('posts')->where('created_at', '<', $cutoff)->where('feed_score', '<>', 0)->update(['feed_score' => 0]);
        DB::table('posts')->whereNotNull('removed_at')->where('feed_score', '<>', 0)->update(['feed_score' => 0]);

        if (DB::getDriverName() === 'mysql') {
            // The current time is passed in (not NOW()) so it matches the UTC timestamps Laravel stores.
            return DB::update(
                'UPDATE posts
                 SET feed_score = (? + engagement_points)
                     / POW(GREATEST(TIMESTAMPDIFF(SECOND, created_at, ?), 0) / 3600 + ?, ?)
                 WHERE removed_at IS NULL AND created_at >= ?',
                [$base, $now->toDateTimeString(), $offset, $gravity, $cutoff->toDateTimeString()],
            );
        }

        // Other databases: same formula in PHP.
        $count = 0;

        DB::table('posts')
            ->whereNull('removed_at')
            ->where('created_at', '>=', $cutoff)
            ->chunkById(500, function ($posts) use (&$count, $now, $base, $gravity, $offset) {
                foreach ($posts as $post) {
                    $hours = max(0, \Illuminate\Support\Carbon::parse($post->created_at)->diffInSeconds($now, false)) / 3600;

                    DB::table('posts')->where('id', $post->id)->update([
                        'feed_score' => FeedScore::score($base, (float) $post->engagement_points, $hours, $gravity, $offset),
                    ]);
                    $count++;
                }
            });

        return $count;
    }
}
