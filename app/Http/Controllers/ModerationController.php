<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Services\ModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ModerationController extends Controller
{
    public function __construct(private ModerationService $moderation)
    {
    }

    public function index(Request $request)
    {
        $role = (string) $request->user()->role;
        $tab = $request->query('tab', $role === 'guidance' ? 'guidance' : 'queue');

        // Guidance only works from the "Referred to Guidance" list.
        if ($role === 'guidance') {
            $tab = 'guidance';
        }

        $tab = in_array($tab, ['queue', 'guidance', 'history'], true) ? $tab : 'queue';
        $severity = in_array($request->query('severity'), ['critical', 'high', 'medium'], true) ? $request->query('severity') : null;

        $cards = $tab === 'history' ? collect() : $this->moderation->queue($tab, $severity)->map(function (array $card) use ($role) {
            $card['actions'] = $this->moderation->actionsFor($role, $card['model']);
            unset($card['model']);

            return $card;
        });

        return view('moderation.index', [
            'tab' => $tab,
            'severity' => $severity,
            'cards' => $cards,
            'history' => $tab === 'history' ? $this->moderation->history() : collect(),
            'role' => $role,
            'categories' => Report::CATEGORIES,
        ]);
    }

    public function act(Request $request, int $report): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['warn', 'remove', 'restore', 'mute', 'ban', 'unmute', 'unban', 'dismiss', 'refer', 'handled'])],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'override_category' => ['nullable', Rule::in(Report::CATEGORIES)],
        ], ['reason.required' => 'Every action needs a reason.', 'reason.min' => 'Every action needs a reason (at least 3 characters).']);

        $this->moderation->act($request->user(), $report, $data['action'], $data['reason'], $data['override_category'] ?? null);

        return back()->with('report_status', 'Done. The action was logged.');
    }

    public function unmask(Request $request, int $report): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $email = $this->moderation->unmask($request->user(), $report, $data['reason']);

        return back()->with('report_status', "Identity unmasked: {$email}");
    }
}
