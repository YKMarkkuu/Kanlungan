<?php

namespace App\Services;

use App\Models\Block;
use App\Models\Bookmark;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\Report;
use App\Support\Alias;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
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
    /** @var array<int, array<int, int>> */
    private array $blockedCache = [];

    public function __construct(
        private ReportService $reports,
        private PointService $points,
        private AlertService $alerts,
    ) {}

    /** @return array{hasPendingReports: bool, posts: \Illuminate\Pagination\CursorPaginator} */
    public function feed(int $viewerId, ?string $category = null): array
    {
        $this->reports->sweepDue();

        $activeReports = Report::query()->whereIn('status', [Report::OPEN, Report::VOTING])->get(['reporter_id', 'reportable_type', 'reportable_id']);
        $reporterPostIds = $activeReports
            ->where('reportable_type', Post::class)
            ->where('reporter_id', $viewerId)
            ->pluck('reportable_id')
            ->all();

        $posts = $this->postQuery($viewerId)
            ->whereNull('removed_at')
            ->whereNotIn('id', $reporterPostIds)
            ->when($category !== null && in_array($category, Post::CATEGORIES, true), fn ($q) => $q->where('category', $category))
            ->latest()
            ->latest('id')
            ->cursorPaginate(15);
        $states = $this->reports->statesFor($viewerId, Post::class, $posts->getCollection());

        return [
            'hasPendingReports' => $activeReports->isNotEmpty(),
            'posts' => $posts->through(fn (Post $post) => $this->postArray($post, $viewerId, $states[$post->id] ?? null)),
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
            ->whereNotIn('author_id', $this->blockedIds($viewerId))
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
        $term = trim($term);

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
            ->whereNotIn('author_id', $this->blockedIds($viewerId))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $states = $this->reports->statesFor($viewerId, Comment::class, $models);

        $all = $models->map(fn (Comment $comment) => $this->commentArray($comment, $viewerId, $states[$comment->id] ?? null));

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

    /** @param  array<int, array{path: string, width: int, height: int}>  $images */
    public function createPost(
        int $authorId,
        string $category,
        string $body,
        bool $anonymous,
        array $images = [],
        ?int $price = null,
        ?string $condition = null,
    ): Post
    {
        return DB::transaction(function () use ($authorId, $category, $body, $anonymous, $images, $price, $condition) {
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

            return $post;
        });
    }

    public function addComment(int $authorId, Post $post, string $body, bool $anonymous, ?int $parentId): Comment
    {
        if (in_array((int) $post->author_id, $this->blockedIds($authorId), true)) {
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
        }

        $comment = Comment::create([
            'post_id' => $post->id,
            'author_id' => $authorId,
            'parent_id' => $parentId,
            'body' => $body,
            'is_anonymous' => $anonymous,
        ]);

        // Alerts say what was written, never who wrote it.
        $url = route('post.show', $post->id).'#comment-'.$comment->id;
        $snippet = '"'.Str::limit($body, 40).'"';
        $recipients = [];

        if ((int) $post->author_id !== $authorId) {
            $recipients[(int) $post->author_id] = 'New comment on your post';
        }

        if ($parent !== null && (int) $parent->author_id !== $authorId) {
            $recipients[(int) $parent->author_id] = 'New reply to your comment';
        }

        foreach ($recipients as $userId => $title) {
            $this->alerts->notify($userId, 'comment', $title, $snippet, $url);
        }

        return $comment;
    }

    /**
     * Switch a reaction on if the user has not given it, off if they have.
     * Returns true when the reaction is ON after the call, false when it is OFF.
     * The author earns points for it (and loses them again if it is taken back).
     * Nobody may react to their own post/comment: the guard below throws before
     * anything is saved, so self-reactions can never inflate the visible counts.
     */
    public function toggleReaction(int $userId, Model $reactable, string $type): bool
    {
        $authorId = (int) $reactable->author_id;

        if ($authorId === $userId) {
            throw ValidationException::withMessages(['report' => 'You cannot react to your own content.']);
        }

        if (in_array($authorId, $this->blockedIds($userId), true)) {
            throw ValidationException::withMessages(['report' => 'You cannot interact with this user.']);
        }

        // Marketplace is transactional, not community support: reactions register, points do not.
        // Both directions use this flag so un-liking never deducts points that were never given.
        $parentPost = $reactable instanceof Post ? $reactable : ($reactable instanceof Comment ? $reactable->post : null);
        $awardsPoints = $parentPost?->category !== Post::MARKETPLACE;

        $existing = $reactable->reactions()->where('user_id', $userId)->where('type', $type)->first();
        $amount = $type === Reaction::HELPFUL ? PointService::HELPFUL_RECEIVED : PointService::LIKE_RECEIVED;
        $name = $type === Reaction::HELPFUL ? 'helpful' : 'like';

        if ($existing !== null) {
            $existing->delete();

            if ($awardsPoints) {
                $this->points->award($authorId, -$amount, $name.'_removed', $reactable);
            }

            return false;
        }

        try {
            $reactable->reactions()->create(['user_id' => $userId, 'type' => $type]);
        } catch (QueryException $e) {
            // A concurrent request may have created this reaction first.
            if (! $reactable->reactions()->where('user_id', $userId)->where('type', $type)->exists()) {
                throw $e;
            }

            return true;
        }

        if ($awardsPoints) {
            $this->points->award($authorId, $amount, $name.'_received', $reactable);
        }

        if ($type === Reaction::HELPFUL && $reactable instanceof Comment) {
            $title = $awardsPoints ? "Your answer was marked helpful +{$amount}" : 'Your answer was marked helpful';
            $this->alerts->notify($authorId, 'helpful', $title, '"'.Str::limit($reactable->body, 40).'"', route('post.show', $reactable->post_id).'#comment-'.$reactable->id);
        }

        return true;
    }

    /**
     * The post author picks (or un-picks) the "Accepted" answer. One per post.
     * The comment's author earns ACCEPTED_RECEIVED points (nothing if it is their own comment).
     */
    public function toggleAccepted(int $userId, Post $post, Comment $comment): void
    {
        if ((int) $post->author_id !== $userId) {
            throw ValidationException::withMessages(['report' => 'Only the author of the post can accept an answer.']);
        }

        if ((int) $comment->post_id !== (int) $post->id || $comment->parent_id !== null || $comment->removed_at !== null) {
            throw ValidationException::withMessages(['report' => 'That comment cannot be accepted.']);
        }

        if ((int) $comment->author_id === $userId) {
            throw ValidationException::withMessages(['report' => 'You cannot accept your own comment.']);
        }

        if ($comment->is_accepted) {
            $this->setAccepted($comment, false);

            return;
        }

        foreach (Comment::where('post_id', $post->id)->where('is_accepted', true)->get() as $previous) {
            $this->setAccepted($previous, false);
        }

        $this->setAccepted($comment, true);
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

    /** @return array<int, int> ids of users the viewer has blocked */
    private function blockedIds(int $viewerId): array
    {
        return $this->blockedCache[$viewerId] ??= Block::where('blocker_id', $viewerId)
            ->pluck('blocked_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function setAccepted(Comment $comment, bool $accepted): void
    {
        $comment->forceFill(['is_accepted' => $accepted])->save();

        $this->points->award((int) $comment->author_id, $accepted ? PointService::ACCEPTED_RECEIVED : -PointService::ACCEPTED_RECEIVED, $accepted ? 'accepted_received' : 'accepted_removed', $comment);

        if ($accepted) {
            $this->alerts->notify((int) $comment->author_id, 'helpful', 'Your answer was marked helpful +'.PointService::ACCEPTED_RECEIVED, '"'.Str::limit($comment->body, 40).'"', route('post.show', $comment->post_id).'#comment-'.$comment->id);
        }
    }

    private function postQuery(int $viewerId)
    {
        return Post::query()
            ->select('posts.*')
            ->with(['author:id,display_name,avatar_path', 'images'])
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
            'removed' => $removed,
            'mine' => (int) $post->author_id === $viewerId,
            'report' => $removed ? null : $report,
            'likes' => (int) $post->likes_count,
            'comments' => (int) $post->comments_count,
            'liked' => (bool) $post->liked,
            'time_ago' => $this->ago($post->created_at),
        ];
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
