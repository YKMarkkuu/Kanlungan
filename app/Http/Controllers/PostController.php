<?php

namespace App\Http\Controllers;

use App\Models\Block;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Reaction;
use App\Services\CommunityService;
use App\Services\ImageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
    public function __construct(private CommunityService $community, private ImageService $images) {}

    public function create()
    {
        return view('feed.create', [
            'categories' => Post::CATEGORIES,
            // Example only: the real alias is per thread, so it is only known once the post exists.
            'alias' => 'Anonymous Falcon #4821',
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(Post::CATEGORIES)],
            'body' => ['required', 'string', 'min:3', 'max:2000'],
            'photos' => ['nullable', 'array', 'max:'.Post::MAX_PHOTOS],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            // TEMPORARY: the old single-photo field, until the new composer UI ships. Remove then.
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'photos.max' => 'You can only upload up to '.Post::MAX_PHOTOS.' photos.',
            'photos.*.max' => 'Each photo must be 4 MB or smaller.',
            'photos.*.image' => 'Please choose JPG, PNG or WebP photos.',
            'photos.*.mimes' => 'Please choose JPG, PNG or WebP photos.',
            'photo.max' => 'The photo must be 4 MB or smaller.',
            'photo.image' => 'Please choose a JPG, PNG or WebP photo.',
        ]);

        // Anonymous is on by default; a missing value means "anonymous".
        $anonymous = $request->input('anonymous', '1') === '1';

        // No 'dimensions' rule any more: big camera photos are shrunk on the server instead of rejected.
        $files = array_values(array_filter([
            ...($request->file('photos') ?? []),
            $request->file('photo'),
        ]));

        if (count($files) > Post::MAX_PHOTOS) {
            throw ValidationException::withMessages(['photos' => 'You can only upload up to '.Post::MAX_PHOTOS.' photos.']);
        }

        $stored = [];

        try {
            foreach ($files as $file) {
                $stored[] = $this->images->storePostPhoto($file);
            }

            $this->community->createPost($request->user()->id, $data['category'], trim($data['body']), $anonymous, $stored);
        } catch (\Throwable $e) {
            // Any failure (bad file 2 of 3, DB error) must not leave orphan files on disk.
            $this->images->deletePaths(array_column($stored, 'path'));

            throw $e;
        }

        if ($request->expectsJson()) {
            $request->session()->flash('status', 'Posted.');

            return response()->json(['status' => 'success', 'redirect' => route('feed')], 201);
        }

        return redirect()->route('feed')->with('status', 'Posted.');
    }

    public function show(Request $request, int $id)
    {
        $userId = $request->user()->id;
        $post = $this->community->post($userId, $id);

        abort_if($post === null, 404);

        return view('feed.show', [
            'post' => $post,
            'comments' => $this->community->comments($userId, $id),
            'alias' => $this->community->aliasIn($userId, $id),
        ]);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $post = Post::findOrFail($id);
        abort_unless((int) $post->author_id === (int) $request->user()->id, 403);

        if ($post->removed_at === null) {
            $post->forceFill(['removed_at' => now(), 'removed_by' => 'author'])->save();
            $this->images->deletePostImages($post);
        }

        return redirect()->route('feed')->with('status', 'Post deleted.');
    }

    public function destroyComment(Request $request, int $id, int $commentId): RedirectResponse
    {
        $comment = Comment::where('post_id', $id)->findOrFail($commentId);
        abort_unless((int) $comment->author_id === (int) $request->user()->id, 403);

        if ($comment->removed_at === null) {
            $comment->forceFill(['removed_at' => now(), 'removed_by' => 'author'])->save();
        }

        return back()->with('status', 'Comment deleted.');
    }

    public function comment(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $post = Post::findOrFail($id);
        abort_if(
            Block::where('blocker_id', $request->user()->id)->where('blocked_id', $post->author_id)->exists(),
            403,
            'You have blocked this user.'
        );

        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:1000'],
            'parent_id' => ['nullable', 'integer', Rule::exists('comments', 'id')->where('post_id', $post->id)],
        ]);

        $anonymous = $request->input('anonymous', '1') === '1';

        $comment = $this->community->addComment($request->user()->id, $post, trim($data['body']), $anonymous, $data['parent_id'] ?? null);

        if ($request->expectsJson()) {
            $card = $this->community->commentCard($request->user()->id, $comment->id);

            return response()->json([
                'status' => 'success',
                'comment_id' => $comment->id,
                'parent_id' => $comment->parent_id,
                'comments_count' => $this->community->commentCount((int) $request->user()->id, (int) $post->id),
                'html' => view('components.comment-card', [
                    'comment' => $card,
                    'reply' => $comment->parent_id !== null,
                    'canAccept' => false,
                ])->render(),
            ], 201);
        }

        return redirect()->route('post.show', $post->id);
    }

    public function acceptComment(Request $request, int $id, int $commentId): RedirectResponse
    {
        $post = Post::findOrFail($id);
        $comment = Comment::where('post_id', $id)->findOrFail($commentId);

        $this->community->toggleAccepted($request->user()->id, $post, $comment);

        return back();
    }

    public function likePost(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $post = Post::findOrFail($id);
        abort_if(
            Block::where('blocker_id', $request->user()->id)->where('blocked_id', $post->author_id)->exists(),
            403,
            'You have blocked this user.'
        );

        $active = $this->community->toggleReaction($request->user()->id, $post, Reaction::LIKE);

        return $this->reactionResponse($request, $post, Reaction::LIKE, $active);
    }

    public function likeComment(Request $request, int $id, int $commentId): RedirectResponse|JsonResponse
    {
        $comment = Comment::where('post_id', $id)->findOrFail($commentId);
        abort_if(
            Block::where('blocker_id', $request->user()->id)->where('blocked_id', $comment->author_id)->exists(),
            403,
            'You have blocked this user.'
        );

        $active = $this->community->toggleReaction($request->user()->id, $comment, Reaction::LIKE);

        return $this->reactionResponse($request, $comment, Reaction::LIKE, $active);
    }

    public function helpfulComment(Request $request, int $id, int $commentId): RedirectResponse|JsonResponse
    {
        $comment = Comment::where('post_id', $id)->findOrFail($commentId);
        abort_if(
            Block::where('blocker_id', $request->user()->id)->where('blocked_id', $comment->author_id)->exists(),
            403,
            'You have blocked this user.'
        );

        $active = $this->community->toggleReaction($request->user()->id, $comment, Reaction::HELPFUL);

        return $this->reactionResponse($request, $comment, Reaction::HELPFUL, $active);
    }

    /**
     * fetch() callers (Accept: application/json) get the fresh count as JSON;
     * plain form posts keep the old redirect. The count is re-queried, so it is
     * the real number even if two people react at once.
     */
    private function reactionResponse(Request $request, Model $target, string $type, bool $active): RedirectResponse|JsonResponse
    {
        if (! $request->expectsJson()) {
            return back();
        }

        return response()->json([
            'status' => 'success',
            'new_count' => $target->reactions()->where('type', $type)->count(),
            'is_active' => $active,
        ]);
    }
}
