<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Services\ReportService;
use App\Support\VoteTally;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports)
    {
    }

    public function store(Request $request, string $type, int $id): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(Report::CATEGORIES)],
            'details' => ['nullable', 'string', 'max:255'],
        ], ['category.required' => 'Pick a reason for your report.']);

        $this->reports->report($request->user()->id, $type, $id, $data['category'], $data['details'] ?? null);

        return back()->with('report_status', 'Report sent. Others can\'t see that you reported.');
    }

    public function start(Request $request, int $report): RedirectResponse
    {
        $this->reports->startVoting($request->user()->id, $report);

        return back()->with('report_status', 'Voting started.');
    }

    public function vote(Request $request, int $report): RedirectResponse
    {
        $data = $request->validate(['choice' => ['required', Rule::in(VoteTally::CHOICES)]]);

        $this->reports->vote($request->user()->id, $report, $data['choice']);

        return back()->with('report_status', 'Vote recorded.');
    }
}
