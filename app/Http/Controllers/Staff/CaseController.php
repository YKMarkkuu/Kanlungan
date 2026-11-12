<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\ModerationService;
use App\Services\StaffDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CaseController extends Controller
{
    public function __construct(private StaffDashboardService $staff, private ModerationService $moderation)
    {
    }

    public function index(Request $request)
    {
        $default = $request->user()->role === 'guidance' ? 'referred' : 'review';
        $view = $request->query('view');
        $view = is_string($view) && array_key_exists($view, StaffDashboardService::VIEWS) ? $view : $default;
        $severity = in_array($request->query('severity'), ['critical', 'high', 'medium'], true) ? $request->query('severity') : null;
        $type = in_array($request->query('type'), ['post', 'comment'], true) ? $request->query('type') : null;

        return view('staff.reports', [
            'view' => $view,
            'severity' => $severity,
            'type' => $type,
            'reports' => $this->staff->list($view, $severity, $type),
            'tabCounts' => $this->staff->tabCounts(),
        ]);
    }

    public function show(Request $request, int $report)
    {
        return view('staff.case', [
            'case' => $this->staff->caseFor($report, $request->user()),
            'muteChoices' => ModerationService::MUTE_CHOICES,
            'categories' => Report::CATEGORIES,
            'labels' => StaffDashboardService::ACTION_LABELS,
        ]);
    }

    public function act(Request $request, int $report): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['warn', 'remove', 'restore', 'mute', 'ban', 'unmute', 'unban', 'dismiss', 'refer', 'handled'])],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'mute_days' => ['nullable', 'integer'],
            // The reporter may have picked the wrong reason; staff can correct it while deciding.
            'override_category' => ['nullable', Rule::in(Report::CATEGORIES)],
        ], ['action.required' => 'Choose what to do using one of the buttons.', 'reason.required' => 'Every action needs a reason.', 'reason.min' => 'Every action needs a reason (at least 3 characters).']);

        $this->moderation->act(
            $request->user(),
            $report,
            $data['action'],
            $data['reason'],
            $data['override_category'] ?? null,
            isset($data['mute_days']) ? (int) $data['mute_days'] : null,
        );

        return redirect()->route('staff.case', $report)->with('staff_status', 'Done. The action was saved to the audit log.');
    }

    public function reveal(Request $request, int $report): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => 'Say why you need to know who this is.']);

        $this->moderation->reveal($request->user(), $report, $data['reason']);

        return redirect()->route('staff.case', $report)->with('staff_status', 'Identity revealed below. This was saved to the audit log.');
    }
}
