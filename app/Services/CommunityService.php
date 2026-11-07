<?php

namespace App\Services;

use App\Services\FeedRankingService;
use App\Models\Block;
use App\Models\Bookmark;
use App\Models\Comment;
use App\Models\Post;
use App\Models\PostItem;
use App\Models\Reaction;
use App\Models\Report;
use App\Models\User;
use App\Support\Alias;
use App\Support\RankedFeedPaginator;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Feed, posts, comments, reactions, search and "my posts / my comments".
 *
 * The read methods return plain arrays shaped like docs/FRONTEND.md, so the
 * Blade files never touch models. author_id is NEVER put in these arrays:
 * peers only see the per-thread alias.
 */
class CommunityService
{
    public function __construct(
        private ReportService $reports,
        private PointService $points,
        private AlertService $alerts,
        private FeedRankingService $ranking,
    ) {}

    /** @return array{hasPendingReports: bool, posts: RankedFeedPaginator} */
    public function feed(int $viewerId, ?string $category = null, ?string $cursor = null): array
    {
        if (Cache::add('reports:sweep', 1, 60)) {
            $this->reports->sweepDue();
        }

        $category = $category !== null && in_array($category, Post::CATEGORIES, true) ? $category : null;
        $page = $this->ranking->pageIds($viewerId, $category, $cursor);

        // Hydrate the page's ids. visible() runs again so a post removed, or reported by this viewer,
        // since the snapshot was frozen disappears immediately.
        $models = $page['ids'] === []
            ? collect()
            : $this->ranking->visible($this->postQuery($viewerId), $viewerId)
                ->whereIn('posts.id', $page['ids'])
                ->get()
                ->keyBy('id');

        $ordered = collect($page['ids'])
            ->map(fn (int $id) => $models->get($id))
            ->filter()
            ->values();

        $states = $this->reports->statesFor($viewerId, Post::class, $ordered);

        return [
            'hasPendingReports' => Report::whereIn('status', [Report::OPEN, Report::VOTING])->exists(),
            'posts' => new RankedFeedPaginator(
                $ordered->map(fn (Post $post) => $this->postArray($post, $viewerId, $states[$post->id] ?? null)),
                max(1, (int) config('feed.snapshot.per_page')),
                $page['has_more'],
                $page['next_cursor'],
                ['path' => request()->url()],
            ),
        ];
    }

    /**
     * Active reports for which the viewer may start voting or cast a vote.
     *
     * @return array<int, array{kind: string, item: array<string, mixed>}>
     */
    public function reportsForVoting(int $viewerId): array
    {
        $this->reports->sweepDue();

        $activeTargets = Report::query()
            ->whereIn('status', [Report::OPEN, Report::VOTING])
            ->select('reportable_type', 'reportable_id')
            ->distinct()
            ->get();
        $postIds = $activeTargets->where('reportable_type', Post::class)->pluck('reportable_id')->all();
        $commentIds = $activeTargets->where('reportable_type', Comment::class)->pluck('reportable_id')->all();

        $posts = $postIds === [] ? collect() : $this->postQuery($viewerId)->whereIn('id', $postIds)->whereNull('removed_at')->get();
        $comments = $commentIds === [] ? collect() : $this->commentQuery($viewerId)
            ->with('post:id,body,removed_at')
            ->whereIn('id', $commentIds)
            ->whereNull('removed_at')
            ->get()
            ->filter(fn (Comment $comment) => $comment->post !== null && $comment->post->removed_at === null);

        $postStates = $this->reports->statesFor($viewerId, Post::class, $posts);
        $commentStates = $this->reports->statesFor($viewerId, Comment::class, $comments);

        $postRows = $posts->map(fn (Post $post) => [
            'kind' => 'post',
            'item' => $this->postArray($post, $viewerId, $postStates[$post->id] ?? null),
        ]);
        $commentRows = $comments->map(fn (Comment $comment) => [
            'kind' => 'comment',
            'item' => $this->commentArray($comment, $viewerId, $commentStates[$comment->id] ?? null),
        ]);

        return $postRows->concat($commentRows)
            ->filter(function (array $row) {
                $report = $row['item']['report'];

                return $report !== null
                    && ! $report['is_author']
                    && ! $report['is_reporter']
                    && ! $report['has_voted']
                    && ($report['can_start'] || $report['can_vote']);
            })
            ->sortBy(fn (array $row) => [($row['item']['report']['status'] ?? null) === Report::VOTING ? 0 : 1, -$row['item']['id']])
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function search(int $viewerId, string $term): array
    {
        $term = mb_substr(trim($term), 0, 80);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $posts = $this->postQuery($viewerId)->whereNull('removed_at')->where('body', 'like', $like)->latest()->latest('id')->limit(30)->get();
        $states = $this->reports->statesFor($viewerId, Post::class, $posts);

        return $posts->map(fn (Post $p) => $this->postArray($p, $viewerId, $states[$p->id] ?? null))->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function myPosts(int $userId): array
    {
        $posts = $this->postQuery($userId)->where('author_id', $userId)->latest()->latest('id')->limit(50)->get();
        $states = $this->reports->statesFor($userId, Post::class, $posts);

        return $posts->map(fn (Post $p) => $this->postArray($p, $userId, $states[$p->id] ?? null))->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function myComments(int $userId): array
    {
        $comments = $this->commentQuery($userId)->with('post:id,body')->where('author_id', $userId)->latest()->latest('id')->limit(50)->get();
        $states = $this->reports->statesFor($userId, Comment::class, $comments);

        return $comments->map(fn (Comment $c) => $this->commentArray($c, $userId, $states[$c->id] ?? null))->all();
    }

    /** @return array<string, mixed>|null */
    public function post(int $viewerId, int $postId): ?array
    {
        $this->reports->sweepDue();

        $post = $this->postQuery($viewerId)->find($postId);

        if ($post === null) {
            return null;
        }

        $states = $this->reports->statesFor($viewerId, Post::class, collect([$post]));

        return $this->postArray($post, $viewerId, $states[$post->id] ?? null);
    }

    /**
     * Top-level comments, each with ['replies' => [...]] (one level only).
     * Reported comments that are still being decided come first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function comments(int $viewerId, int $postId): array
    {
        $models = $this->commentQuery($viewerId)
            ->where('post_id', $postId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $states = $this->reports->statesFor($viewerId, Comment::class, $models);

        $all = $models
            ->map(fn (Comment $comment) => $this->commentArray($comment, $viewerId, $states[$comment->id] ?? null));

        $replies = $all->whereNotNull('parent_id')->groupBy('parent_id');

        return $all->whereNull('parent_id')
            ->map(function (array $comment) use ($replies) {
                $comment['replies'] = ($replies[$comment['id']] ?? collect())->values()->all();

                return $comment;
            })
            ->sortBy(fn (array $c) => [$c['report'] ? 0 : 1, $c['id']])
            ->values()
            ->all();
    }

    /**
     * One comment as a card array (same shape as comments()), for rendering a freshly posted comment.
     *
     * @return array<string, mixed>
     */
    public function commentCard(int $viewerId, int $commentId): array
    {
        $comment = $this->commentQuery($viewerId)->findOrFail($commentId);

        return $this->commentArray($comment, $viewerId, null);
    }

    /** The alias this viewer has (or will get) inside a given thread. */
    public function aliasIn(int $viewerId, int $postId): string
    {
        return Alias::for($viewerId, $postId);
    }

    public function commentCount(int $viewerId, int $postId): int
    {
        return Comment::query()
            ->where('post_id', $postId)
            ->count();
    }

    /**
     * @param  array<int, array{path: string, width: int, height: int}>  $images
     * @param  array<int, array{label: ?string, price: int}>  $items
     */
    public function createPost(
        int $authorId,
        string $category,
        string $body,
        bool $anonymous,
        array $images = [],
        ?int $price = null,
        ?string $condition = null,
        array $items = [],
    ): Post
    {
        return DB::transaction(function () use ($authorId, $category, $body, $anonymous, $images, $price, $condition, $items) {
            $post = Post::create([
                'author_id' => $authorId,
                'category' => $category,
                'body' => $body,
                'price' => $price,
                'condition' => $condition,
                'is_anonymous' => $anonymous,
            ]);

            foreach (array_values($images) as $position => $image) {
                $post->images()->create([
                    'path' => $image['path'],
                    'position' => $position,
                    'width' => $image['width'],
                    'height' => $image['height'],
                ]);
            }

            foreach (array_values($items) as $position => $item) {
                $post->items()->create([
                    'position' => $position,
                    'label' => $item['label'],
                    'price' => $item['price'],
                ]);
            }

            return $post;
        });
    }

    public function addComment(int $authorId, Post $post, string $body, bool $anonymous, ?int $parentId): Comment
    {
        if (Block::existsBetween($authorId, (int) $post->author_id)) {
            throw ValidationException::withMessages(['report' => 'You cannot interact with this user.']);
        }

        if ($post->removed_at !== null) {
            throw ValidationException::withMessages(['report' => 'This post was removed, so it cannot get new comments.']);
        }

        $parent = null;

        // One reply level: replying to a reply attaches to that reply's parent.
        if ($parentId !== null) {
            $found = Comment::where('post_id', $post->id)->find($parentId);
            $parent = $found === null ? null : ($found->parent_id === null ? $found : Comment::find($found->parent_id));
            $parentId = $parent?->id;

            if ($parent !== null && Block::existsBetween($authorId, (int) $parent->author_id)) {
                throw ValidationException::withMessages(['report' => 'You cannot interact with this user.']);
            }
        }

        $comment = Comment::create([
            'post_id' => $post->id,
            'author_id' => $authorId,
            'parent_id' => $parentId,
            'body' => $body,
            'is_anonymous' => $anonymous,
        ]);

        $this->ranking->recomputeEngagement((int) $post->id);

        // Alerts name the commenter exactly as the thread does (alias or display name) and quote the comment.
        $commenterName = $this->displayName(
            $anonymous,
            User::whereKey($authorId)->value('display_name'),
            $authorId,
            (int) $post->id,
        );
        $url = route('post.show', $post->id).'#comment-'.$comment->id;
        $recipients = [];

        if ((int) $post->author_id !== $authorId) {
            $recipients[(int) $post->author_id] = "New comment from {$commenterName}";
        }

        if ($parent !== null && (int) $parent->author_id !== $authorId) {
            $recipients[(int) $parent->author_id] = "{$commenterName} replied to your comment";
        }

        foreach ($recipients as $userId => $title) {
            $this->alerts->notify($userId, 'comment', $title, null, $url, $body);
        }

        return $comment;
    }

    /**
     * Switch a reaction on if the user has not given it, off if they have.
     * Returns true when the reaction is ON after the call, false when it is OFF.
     * Helpful reactions on eligible comments earn points; Likes are cosmetic.
     * Nobody may react to their own post/comment: the guard below throws before
     * anything is saved, so self-reactions can never inflate the visible counts.
     */
    public function toggleReaction(int $userId, Model $reactable, string $type): bool
    {
        $parentPost = $reactable instanceof Post ? $reactable : $reactable->post;
        $authorId = (int) $reactable->author_id;

        if ($authorId === $userId) {
            throw ValidationException::withMessages(['report' => 'You cannot react to your own content.']);
        }

        if (Block::existsBetween($userId, $authorId)) {
            throw ValidationException::withMessages(['report' => 'You cannot interact with this user.']);
        }

        if ($parentPost->removed_at !== null) {
            throw ValidationException::withMessages(['report' => 'This post was removed.']);
        }

        $active = DB::transaction(function () use ($userId, $reactable, $authorId, $type) {
            $existing = $reactable->reactions()->where('user_id', $userId)->where('type', $type)->first();
            $isHelpfulComment = $type === Reaction::HELPFUL && $reactable instanceof Comment;

            if ($existing !== null) {
                $deleted = $reactable->reactions()->whereKey($existing->getKey())->delete();

                if ($deleted > 0 && $isHelpfulComment) {
                    $this->points->reverse($authorId, 'helpful_removed', $reactable);
                }

                return false;
            }

            $reaction = $reactable->reactions()->createOrFirst(['user_id' => $userId, 'type' => $type]);

            if (! $reaction->wasRecentlyCreated) {
                return true;
            }

            if ($isHelpfulComment) {
                $this->points->awardHelpful($authorId, $reactable);
                $this->alerts->notify(
                    $authorId,
                    'helpful',
                    'Your answer was marked helpful',
                    null,
                    route('post.show', $reactable->post_id).'#comment-'.$reactable->id,
                    $reactable->body,
                );
            }

            return true;
        });

        // Only post likes and comment "helpful" marks feed the score.
        if (($type === Reaction::LIKE && $reactable instanceof Post) || ($type === Reaction::HELPFUL && $reactable instanceof Comment)) {
            $this->ranking->recomputeEngagement($reactable instanceof Post ? (int) $reactable->id : (int) $reactable->post_id);
        }

        return $active;
    }

    /**
     * The post author picks (or un-picks) the "Accepted" answer. One per post.
     * The comment's author earns ACCEPTED_RECEIVED points only for eligible post categories.
     */
    public function toggleAccepted(int $userId, Post $post, Comment $comment): void
    {
        DB::transaction(function () use ($userId, $post, $comment) {
            $post = Post::query()->lockForUpdate()->findOrFail($post->id);
            $comment = Comment::query()->lockForUpdate()->findOrFail($comment->id);

            if ($post->removed_at !== null) {
                throw ValidationException::withMessages(['report' => 'This post was removed.']);
            }

            if ((int) $post->author_id !== $userId) {
                throw ValidationException::withMessages(['report' => 'Only the author of the post can accept an answer.']);
            }

            if ((int) $comment->post_id !== (int) $post->id || $comment->parent_id !== null || $comment->removed_at !== null) {
                throw ValidationException::withMessages(['report' => 'That comment cannot be accepted.']);
            }

            if ((int) $comment->author_id === $userId) {
                throw ValidationException::withMessages(['report' => 'You cannot accept your own comment.']);
            }

            $awardsPoints = $this->points->awardsPoints($post);

            if ($comment->is_accepted) {
                $this->setAccepted($comment, false, $awardsPoints);

                return;
            }

            foreach (Comment::query()->where('post_id', $post->id)->where('is_accepted', true)->lockForUpdate()->get() as $previous) {
                $this->setAccepted($previous, false, $awardsPoints);
            }

            $this->setAccepted($comment, true, $awardsPoints);
        });

        $this->ranking->recomputeEngagement((int) $post->id);
    }

    /**
     * Save / un-save a post for this viewer. Returns true when it is saved after the call.
     * Un-saving always works (even for a removed post); saving a removed post is refused.
     */
    public function toggleBookmark(int $userId, Post $post): bool
    {
        $existing = Bookmark::where('user_id', $userId)->where('post_id', $post->id)->first();

        if ($existing !== null) {
            $existing->delete();

            return false;
        }

        if ($post->removed_at !== null) {
            throw ValidationException::withMessages(['report' => 'This post was removed, so it cannot be saved.']);
        }

        try {
            Bookmark::create(['user_id' => $userId, 'post_id' => $post->id]);
        } catch (QueryException $e) {
            if (! Bookmark::where('user_id', $userId)->where('post_id', $post->id)->exists()) {
                throw $e;
            }
        }

        return true;
    }

    /** @return array<int, array<string, mixed>> the viewer's saved posts, most recently saved first */
    public function bookmarkedPosts(int $userId): array
    {
        $posts = $this->postQuery($userId)
            ->join('bookmarks as saved', 'saved.post_id', '=', 'posts.id')
            ->where('saved.user_id', $userId)
            ->whereNull('posts.removed_at')
            ->orderByDesc('saved.created_at')
            ->orderByDesc('saved.id')
            ->limit(50)
            ->get();

        $states = $this->reports->statesFor($userId, Post::class, $posts);

        return $posts->map(fn (Post $post) => $this->postArray($post, $userId, $states[$post->id] ?? null))->all();
    }

    /* ---------- internals ---------- */

    private function setAccepted(Comment $comment, bool $accepted, bool $awardsPoints): void
    {
        $comment->forceFill(['is_accepted' => $accepted])->save();

        if ($accepted) {
            if ($awardsPoints) {
                $this->points->award((int) $comment->author_id, PointService::ACCEPTED_RECEIVED, 'accepted_received', $comment);
            }
        } else {
            $this->points->reverse((int) $comment->author_id, 'accepted_removed', $comment);
        }

        if ($accepted) {
            $title = 'Your answer was marked helpful'.($awardsPoints ? ' +'.PointService::ACCEPTED_RECEIVED : '');
            $this->alerts->notify(
                (int) $comment->author_id,
                'helpful',
                $title,
                null,
                route('post.show', $comment->post_id).'#comment-'.$comment->id,
                $comment->body,
            );
        }
    }

    private function postQuery(int $viewerId)
    {
        return Post::query()
            ->select('posts.*')
            ->with(['author:id,display_name,avatar_path', 'images', 'items'])
            ->withCount([
                'comments',
                'reactions as likes_count' => fn ($q) => $q->where('type', Reaction::LIKE),
            ])
            ->withExists([
                'reactions as liked' => fn ($q) => $q->where('type', Reaction::LIKE)->where('user_id', $viewerId),
                'bookmarks as bookmarked' => fn ($q) => $q->where('user_id', $viewerId),
            ]);
    }

    private function commentQuery(int $viewerId)
    {
        return Comment::query()
            ->with('author:id,display_name,avatar_path')
            ->with('post:id,author_id')
            ->withCount([
                'reactions as likes_count' => fn ($q) => $q->where('type', Reaction::LIKE),
                'reactions as helpful_count' => fn ($q) => $q->where('type', Reaction::HELPFUL),
            ])
            ->withExists([
                'reactions as liked' => fn ($q) => $q->where('type', Reaction::LIKE)->where('user_id', $viewerId),
                'reactions as helpful_marked' => fn ($q) => $q->where('type', Reaction::HELPFUL)->where('user_id', $viewerId),
            ]);
    }

    /** @return array<string, mixed> */
    private function postArray(Post $post, int $viewerId, ?array $report): array
    {
        $removed = $post->removed_at !== null;
        $images = $removed ? [] : $post->images
            ->map(fn ($image) => ['path' => $image->path, 'width' => $image->width, 'height' => $image->height])
            ->all();
        $items = $removed ? [] : $this->itemsFor($post);

        return [
            'id' => $post->id,
            'avatar_path' => ! $post->is_anonymous && ! $removed ? $post->author?->avatar_path : null,
            'author_alias' => $this->displayName($post->is_anonymous, $post->author?->display_name, $post->author_id, $post->id),
            'is_anonymous' => $post->is_anonymous,
            'category' => $post->category,
            'created_at' => $post->created_at,
            'body' => $removed ? $this->tombstone('post', $post->removed_by) : $post->body,
            'images' => $images,
            'image' => $images[0]['path'] ?? null, // TEMPORARY: lets the current post-card keep working. Remove with the Blade update.
            'has_bookmarked' => (bool) $post->bookmarked,
            'is_marketplace' => $post->category === Post::MARKETPLACE,
            'is_meme' => $post->category === Post::MEME,
            'price' => $removed ? null : $post->price,
            'condition' => $removed ? null : $post->condition,
            'items' => $items,
            'item_count' => count($items),
            'price_text' => self::priceText(array_column($items, 'price')),
            'price_label' => $this->priceLabel($items),
            'removed' => $removed,
            'removed_by' => $removed ? $post->removed_by : null,
            'mine' => (int) $post->author_id === $viewerId,
            'report' => $removed ? null : $report,
            'likes' => (int) $post->likes_count,
            'comments' => (int) $post->comments_count,
            'liked' => (bool) $post->liked,
            'time_ago' => $this->ago($post->created_at),
        ];
    }

    /**
     * @param  array<int, int|string>  $prices
     */
    public static function priceText(array $prices): ?string
    {
        $prices = array_values(array_map('intval', $prices));

        if ($prices === []) {
            return null;
        }

        $min = min($prices);
        $max = max($prices);
        $format = fn (int $price) => $price === 0 ? 'Free' : '₱'.number_format($price);

        if ($min === $max) {
            if ($min === 0) {
                return 'Free';
            }

            return count($prices) > 1 ? $format($min).' each' : $format($min);
        }

        return $format($min).' – '.$format($max);
    }

    /**
     * A single-item listing has no post_items rows, so rebuild it from posts.price.
     *
     * @return array<int, array{label: ?string, price: int}>
     */
    private function itemsFor(Post $post): array
    {
        if ($post->category !== Post::MARKETPLACE) {
            return [];
        }

        if ($post->items->isNotEmpty()) {
            return $post->items
                ->map(fn (PostItem $item) => ['label' => $item->label, 'price' => (int) $item->price])
                ->all();
        }

        return $post->price !== null ? [['label' => null, 'price' => (int) $post->price]] : [];
    }

    /** Screen-reader text for a price badge, spelling out each item where available. */
    private function priceLabel(array $items): ?string
    {
        if ($items === []) {
            return null;
        }

        $pesos = fn (int $price) => $price === 0 ? 'free' : number_format($price).' pesos';

        if (count($items) === 1) {
            return 'Price: '.$pesos($items[0]['price']);
        }

        return count($items).' items: '.implode(', ', array_map(
            fn (array $item) => ($item['label'] ?? 'Item').' '.$pesos($item['price']),
            $items,
        ));
    }

    /** @return array<string, mixed> */
    private function commentArray(Comment $comment, int $viewerId, ?array $report): array
    {
        $removed = $comment->removed_at !== null;

        return [
            'id' => $comment->id,
            'post_id' => $comment->post_id,
            'parent_id' => $comment->parent_id,
            'avatar_path' => ! $comment->is_anonymous && ! $removed ? $comment->author?->avatar_path : null,
            'created_at' => $comment->created_at,
            'post_excerpt' => $comment->relationLoaded('post') && $comment->post !== null ? Str::limit($comment->post->body, 70) : null,
            'author_alias' => $this->displayName($comment->is_anonymous, $comment->author?->display_name, $comment->author_id, $comment->post_id),
            'is_op' => $comment->relationLoaded('post')
                && $comment->post !== null
                && (int) $comment->author_id === (int) $comment->post->author_id,
            'is_anonymous' => $comment->is_anonymous,
            'body' => $removed ? $this->tombstone('comment', $comment->removed_by) : $comment->body,
            'removed' => $removed,
            'mine' => (int) $comment->author_id === $viewerId,
            'report' => $removed ? null : $report,
            'likes' => (int) $comment->likes_count,
            'liked' => (bool) $comment->liked,
            'helpful_count' => (int) $comment->helpful_count,
            'helpful_marked' => (bool) $comment->helpful_marked,
            'is_accepted' => $comment->is_accepted,
            'time_ago' => $this->ago($comment->created_at),
        ];
    }

    private function tombstone(string $kind, ?string $by): string
    {
        return match ($by) {
            'moderator' => "This {$kind} was removed by moderators.",
            'author' => "This {$kind} was removed by its author.",
            default => "This {$kind} was removed by community vote.",
        };
    }

    /** Anonymous => alias. Not anonymous => display name, or the alias if none is set (docs/AUTH.md). */
    private function displayName(bool $anonymous, ?string $displayName, int $authorId, int $postId): string
    {
        if (! $anonymous && $displayName !== null && trim($displayName) !== '') {
            return $displayName;
        }

        return Alias::for($authorId, $postId);
    }

    private function ago(?CarbonInterface $date): string
    {
        if ($date === null || abs($date->diffInSeconds()) < 60) {
            return 'now';
        }

        return $date->diffForHumans(null, CarbonInterface::DIFF_ABSOLUTE, true);
    }
}
