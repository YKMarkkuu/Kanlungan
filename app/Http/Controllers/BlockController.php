<?php

namespace App\Http\Controllers;

use App\Models\Block;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BlockController extends Controller
{
    private const TYPES = ['post' => Post::class, 'comment' => Comment::class];

    /**
     * Block the author of a post/comment. The client only ever sends the
     * post/comment id; the author id never reaches the browser (anonymity).
     */
    public function store(Request $request, string $type, int $id): RedirectResponse
    {
        $class = self::TYPES[$type] ?? null;
        abort_if($class === null, 404);

        $target = $class::findOrFail($id);
        $blockerId = (int) $request->user()->id;
        $blockedId = (int) $target->author_id;

        if ($blockedId === $blockerId) {
            throw ValidationException::withMessages(['report' => 'You cannot block yourself.']);
        }

        try {
            Block::firstOrCreate(['blocker_id' => $blockerId, 'blocked_id' => $blockedId]);
        } catch (QueryException $e) {
            // A concurrent request created the same pair first; the unique index did its job.
        }

        $message = 'Blocked. You can still see their posts and comments, but you cannot interact with them.';

        // Blocking from a post page would send you back to a 404, so go to the feed.
        if ($type === 'comment') {
            return back()->with('report_status', $message);
        }

        return redirect()->route('feed')->with('report_status', $message);
    }

    public function destroy(Request $request, int $block): RedirectResponse
    {
        Block::where('blocker_id', $request->user()->id)->findOrFail($block)->delete();

        return back()->with('report_status', 'Unblocked.');
    }
}
