<?php

namespace App\Support;

/**
 * Demo data for the feed / create post / post + comments screens.
 *
 * There is NO database behind this yet. Base posts are hard-coded; anything a
 * user posts or comments is kept in their session, so it disappears when the
 * session ends. Arrays follow the "Data shape contract" in docs/FRONTEND.md,
 * so when posts and comments get real tables, replace the methods here with
 * Eloquent queries and the Blade files do not change.
 */
class DemoCommunity
{
    /** The viewer's per-thread alias (a placeholder until alias generation exists). */
    public const ALIAS = 'Anonymous Falcon #4821';

    public const FEED_CATEGORIES = ['Ask', 'Share', 'Help', 'Vent', 'Academic'];

    public const CREATE_CATEGORIES = ['Ask', 'Share', 'Help', 'Vent', 'Academic'];

    private static function basePosts(): array
    {
        return [
            ['id' => 1, 'author_alias' => 'Anonymous Falcon #4821', 'is_anonymous' => true, 'category' => 'Ask', 'body' => 'How do I become a student assistant for ITPM? Is there a form or do I ask the professor directly?', 'likes' => 12, 'time_ago' => '5m'],
            ['id' => 2, 'author_alias' => 'Mika R.', 'is_anonymous' => false, 'category' => 'Academic', 'body' => 'Sharing my reviewer for the midterms. Hope it helps someone.', 'likes' => 3, 'time_ago' => '1h'],
        ];
    }

    private static function baseComments(int $postId): array
    {
        if ($postId !== 1) {
            return [];
        }

        return [
            ['id' => 1, 'post_id' => 1, 'parent_id' => null, 'author_alias' => 'Anonymous Owl #1093', 'is_anonymous' => true, 'body' => 'Try checking the handbook, then ask your department office.', 'is_helpful' => true, 'helpful_count' => 6, 'is_accepted' => true, 'time_ago' => '1h'],
            ['id' => 2, 'post_id' => 1, 'parent_id' => 1, 'author_alias' => 'Anonymous Falcon #4821', 'is_anonymous' => true, 'body' => 'Thank you! I will do that tomorrow.', 'is_helpful' => false, 'helpful_count' => 0, 'is_accepted' => false, 'time_ago' => '50m'],
            ['id' => 3, 'post_id' => 1, 'parent_id' => null, 'author_alias' => 'Juan D.', 'is_anonymous' => false, 'body' => 'Ask your section adviser. They usually know who is hiring.', 'is_helpful' => true, 'helpful_count' => 2, 'is_accepted' => false, 'time_ago' => '30m'],
        ];
    }

    /** Newest first: session posts on top, then the base posts. */
    public static function posts(?string $category = null): array
    {
        $posts = array_merge(array_reverse(session('demo.posts', [])), self::basePosts());

        foreach ($posts as &$post) {
            $post['comments'] = count(self::allComments($post['id']));
        }
        unset($post);

        if ($category !== null && in_array($category, self::FEED_CATEGORIES, true)) {
            $posts = array_values(array_filter($posts, fn ($p) => $p['category'] === $category));
        }

        return $posts;
    }

    public static function find(int $id): ?array
    {
        foreach (self::posts() as $post) {
            if ($post['id'] === $id) {
                return $post;
            }
        }

        return null;
    }

    public static function addPost(string $category, string $body, bool $anonymous): void
    {
        $id = 100 + count(session('demo.posts', []));

        session()->push('demo.posts', [
            'id' => $id,
            'author_alias' => $anonymous ? self::ALIAS : 'You',
            'is_anonymous' => $anonymous,
            'category' => $category,
            'body' => $body,
            'likes' => 0,
            'time_ago' => 'now',
        ]);
    }

    private static function allComments(int $postId): array
    {
        return array_merge(self::baseComments($postId), session("demo.comments.$postId", []));
    }

    /** Comments grouped for display: top-level comments, each with ['replies' => [...]]. One reply level only. */
    public static function comments(int $postId): array
    {
        $all = self::allComments($postId);
        $top = [];

        foreach ($all as $comment) {
            if ($comment['parent_id'] === null) {
                $comment['replies'] = array_values(array_filter($all, fn ($c) => $c['parent_id'] === $comment['id']));
                $top[] = $comment;
            }
        }

        return $top;
    }

    public static function addComment(int $postId, string $body, bool $anonymous, ?int $parentId): void
    {
        $all = self::allComments($postId);

        // One reply level: replying to a reply attaches to that reply's parent.
        if ($parentId !== null) {
            $parent = collect($all)->firstWhere('id', $parentId);
            $parentId = $parent === null ? null : ($parent['parent_id'] ?? $parent['id']);
        }

        session()->push("demo.comments.$postId", [
            'id' => 100 + count($all),
            'post_id' => $postId,
            'parent_id' => $parentId,
            'author_alias' => $anonymous ? self::ALIAS : 'You',
            'is_anonymous' => $anonymous,
            'body' => $body,
            'is_helpful' => false,
            'helpful_count' => 0,
            'is_accepted' => false,
            'time_ago' => 'now',
        ]);
    }
}
