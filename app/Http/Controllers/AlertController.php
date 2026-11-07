<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Services\CommunityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request, CommunityService $community)
    {
        $userId = (int) $request->user()->id;
        $alerts = Alert::where('user_id', $request->user()->id)->latest()->latest('id')->limit(50)->get()
            ->map(fn (Alert $a) => [
                'id' => $a->id,
                'kind' => $a->kind,
                'icon' => $a->iconName(),
                'title' => $a->title,
                'snippet' => $a->snippet,
                'body' => $a->body,
                'unread' => $a->read_at === null,
                'time' => $this->ago($a->created_at),
            ]);

        $reportedItems = $community->reportsForVoting($userId);
        $reports = Report::query()
            ->where('reporter_id', $userId)
            ->latest()
            ->latest('id')
            ->limit(50)
            ->get();

        $postPreviews = Post::query()
            ->whereIn('id', $reports->where('reportable_type', Post::class)->pluck('reportable_id'))
            ->get(['id', 'body', 'removed_at', 'removed_by'])
            ->keyBy('id');
        $commentPreviews = Comment::query()
            ->whereIn('id', $reports->where('reportable_type', Comment::class)->pluck('reportable_id'))
            ->get(['id', 'body', 'removed_at', 'removed_by'])
            ->keyBy('id');

        $myReports = $reports->map(function (Report $report) use ($postPreviews, $commentPreviews) {
            $target = match ($report->reportable_type) {
                Post::class => $postPreviews->get($report->reportable_id),
                Comment::class => $commentPreviews->get($report->reportable_id),
                default => null,
            };

            if ($report->outcome === 'moderator_reviewed') {
                $status = 'Reviewed by Moderator';
                $statusClass = 'neutral';
            } elseif ($report->outcome === 'author_deleted') {
                $status = 'Closed because the author deleted their account';
                $statusClass = 'neutral';
            } elseif ($target?->removed_by === 'moderator') {
                $status = 'Removed by Moderator';
                $statusClass = 'removed';
            } elseif ($report->status === Report::ESCALATED) {
                $status = 'Under moderator review';
                $statusClass = 'pending';
            } elseif ($report->status !== Report::RESOLVED) {
                $status = $report->status === Report::VOTING ? 'Voting in progress' : 'Awaiting community review';
                $statusClass = 'pending';
            } else {
                [$status, $statusClass] = match ($report->outcome) {
                    'upheld' => ['Removed after community vote', 'removed'],
                    'wrong_tag' => ['Removed after community vote (wrong category)', 'removed'],
                    'false_report', 'moderator_cleared' => ['Dismissed / No Violation', 'dismissed'],
                    'no_consensus' => ['No consensus', 'neutral'],
                    default => ['Resolved', 'neutral'],
                };
            }

            return [
                'preview' => $target?->body ?? 'Content unavailable.',
                'reported_at' => $report->created_at?->format('M j, Y'),
                'status' => $status,
                'status_class' => $statusClass,
            ];
        });

        return view('alerts.index', [
            'alerts' => $alerts,
            'reportedItems' => $reportedItems,
            'myReports' => $myReports,
        ]);
    }

    /** Tap an alert: mark it read, then open the post (or the notice with the reason). */
    public function open(Request $request, int $id)
    {
        $alert = Alert::where('user_id', $request->user()->id)->findOrFail($id);

        if ($alert->read_at === null) {
            $alert->update(['read_at' => now()]);
        }

        if ($alert->url !== null) {
            $path = parse_url($alert->url, PHP_URL_PATH) ?: '/';
            $path = '/'.ltrim(str_replace('\\', '/', $path), '/');
            $query = parse_url($alert->url, PHP_URL_QUERY);
            $fragment = parse_url($alert->url, PHP_URL_FRAGMENT);

            if ($query !== null) {
                $path .= '?'.$query;
            }

            if ($fragment !== null) {
                $path .= '#'.$fragment;
            }

            return redirect($path);
        }

        return view('alerts.notice', ['alert' => $alert]);
    }

    public function readAll(Request $request): RedirectResponse
    {
        Alert::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => Alert::unreadCount((int) $request->user()->id),
        ]);
    }

    /** "Just now", "5m ago", "2h ago", "3d ago". */
    private function ago(?\Carbon\CarbonInterface $at): string
    {
        if ($at === null || abs($at->diffInSeconds()) < 60) {
            return 'Just now';
        }

        return $at->diffForHumans(null, null, true);
    }
}
