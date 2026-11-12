<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Services\ModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DpoRevealController extends Controller
{
    public function __construct(private ModerationService $moderation)
    {
    }

    public function show(): View
    {
        return view('staff.dpo-reveal');
    }

    public function reveal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'report_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'reason.required' => 'Say why you need to know who this is.',
            'reason.min' => 'Every reveal needs a reason (at least 10 characters).',
        ]);

        $email = $this->moderation->unmask($request->user(), (int) $data['report_id'], $data['reason']);

        return redirect()->route('staff.dpo-reveal')->with('revealed_identity', [
            'report_id' => (int) $data['report_id'],
            'email' => $email,
        ]);
    }
}
