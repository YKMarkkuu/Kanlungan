<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\Report;
use App\Support\Alias;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
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
    private const FEED_LIMIT = 50;

    public function __construct(
        private ReportService $reports,
        private PointService $points,
        private AlertService $alerts,
    ) {
    }

    /**
     * Reported posts/comments that are still open or being voted on come first for EVERY student
     * ("pinned"), then the normal feed, newest first.
     *
     * @return array{pinned: array<int, array{kind: string, item: array<string, mixed>}>, posts: array<int, array<string, mixed>>}
     */
    public function feed(int $viewerId, ?string $category = null): array
    {
        $this->reports->sweepDue();

        $active = Report::query()->where('status', '!=', Report::RESOLVED)->get(['reportable_type', 'reportable_id']);
        $pinnedPostIds = $active->where('reportable_type', Post::class)->pluck('reportable_id')->all();
        $pinnedCommentIds = $active->where('reportable_type', Comment::class)->pluck('reportable_id')->all();

        $pinnedPosts = $pinnedPostIds === [] ? collect() : $this->postQuery($viewerId)->whereIn('id', $pinnedPostIds)->whereNull('removed_at')->get();
        $pinnedComments = $pinnedCommentIds === [] ? collect() : $this->commentQuery($viewerId)
            ->with('post:id,body,removed_at')
            ->whereIn('id', $pinnedCommentIds)->whereNull('removed_at')->get()
            ->filter(fn (Comment $c) => $c->post !== null && $c->post->removed_at === null);

        $postStates = $this->reports->statesFor($viewerId, Post::class, $pinnedPosts);
        $commentStates = $this->reports->statesFor($viewerId, Comment::class, $pinnedComments);

        $pinned = $pinnedPosts->map(fn (Post $p) => ['kind' => 'post', 'item' => $this->postArray($p, $viewerId, $postStates[$p->id] ?? null)])
            ->concat($pinnedComments->map(fn (Comment $c) => ['kind' => 'comment', 'item' => $this->commentArray($c, $viewerId, $commentStates[$c->id] ?? null)]))
            ->sortBy(fn (array $row) => [($row['item']['report']['status'] ?? null) === Report::VOTING ? 0 : 1, -$row['item']['id']])
            ->values()
            ->all();

        $posts = $this->postQuery($viewerId)
            ->whereNull('removed_at')
            ->whereNotIn('id', $pinnedPostIds)
            ->when($category !== null && in_array($category, Post::CATEGORIES, true), fn ($q) => $q->where('category', $category))
            ->latest()
            ->latest('id')
            ->limit(self::FEED_LIMIT)
            ->get();

        $states = $this->reports->statesFor($viewerId, Post::class, $posts);

        return [
            'pinned' => $pinned,
            'posts' => $posts->map(fn (Post $post) => $this->postArray($post, $viewerId, $states[$post->id] ?? null))->all(),
        ];
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
        $models = $this->commentQuery($viewerId)->where('post_id', $postId)->orderBy('created_at')->orderBy('id')->get();

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

    /** The alias this viewer has (or will get) inside a given thread. */
    public function aliasIn(int $viewerId, int $postId): string
    {
        return Alias::for($viewerId, $postId);
    }

    public function createPost(int $authorId, string $category, string $body, bool $anonymous, ?string $imagePath = null): Post
    {
        return Post::create([
            'author_id' => $authorId,
            'category' => $category,
            'body' => $body,
            'is_anonymous' => $anonymous,
            'image_path' => $imagePath,
        ]);
    }

    public function addComment(int $authorId, Post $post, string $body, bool $anonymous, ?int $parentId): Comment
    {
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
     * The author earns points for it (and loses them again if it is taken back).
     * Rating your own post/comment gives no points.
     */
    public function toggleReaction(int $userId, Model $reactable, string $type): void
    {
        $existing = $reactable->reactions()->where('user_id', $userId)->where('type', $type)->first();
        $authorId = (int) $reactable->author_id;
        $amount = $type === Reaction::HELPFUL ? PointService::HELPFUL_RECEIVED : PointService::LIKE_RECEIVED;
        $name = $type === Reaction::HELPFUL ? 'helpful' : 'like';

        if ($existing !== null) {
            $existing->delete();

            if ($authorId !== $userId) {
                $this->points->award($authorId, -$amount, $name.'_removed', $reactable);
            }

            return;
        }

        try {
            $reactable->reactions()->create(['user_id' => $userId, 'type' => $type]);
        } catch (QueryException $e) {
            // Double-click race: the unique index already has this reaction. Nothing to do.
            return;
        }

        if ($authorId !== $userId) {
            $this->points->award($authorId, $amount, $name.'_received', $reactable);

            if ($type === Reaction::HELPFUL && $reactable instanceof Comment) {
                $this->alerts->notify($authorId, 'helpful', "Your answer was marked helpful +{$amount}", '"'.Str::limit($reactable->body, 40).'"', route('post.show', $reactable->post_id).'#comment-'.$reactable->id);
            }
        }
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

    /* ---------- internals ---------- */

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
            ->with('author:id,display_name')
            ->withCount([
                'comments',
                'reactions as likes_count' => fn ($q) => $q->where('type', Reaction::LIKE),
            ])
            ->withExists([
                'reactions as liked' => fn ($q) => $q->where('type', Reaction::LIKE)->where('user_id', $viewerId),
            ]);
    }

    private function commentQuery(int $viewerId)
    {
        return Comment::query()
            ->with('author:id,display_name')
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

        return [
            'id' => $post->id,
            'author_alias' => $this->displayName($post->is_anonymous, $post->author?->display_name, $post->author_id, $post->id),
            'is_anonymous' => $post->is_anonymous,
            'category' => $post->category,
            'body' => $removed ? $this->tombstone('post', $post->removed_by) : $post->body,
            'image' => $removed || $post->image_path === null ? null : $post->image_path,
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
        return $by === 'moderator' ? "This {$kind} was removed by moderators." : "This {$kind} was removed by community vote.";
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
