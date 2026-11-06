<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Services\StaffDashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private StaffDashboardService $staff)
    {
    }

    public function overview(Request $request)
    {
        // Guidance works mainly from the cases referred to them; everyone else from the decision queue.
        $focus = $request->user()->role === 'guidance' ? 'referred' : 'review';
        $reasons = $this->staff->reasons();

        return view('staff.overview', [
            'counts' => $this->staff->counts(),
            'focus' => $focus,
            'waiting' => $this->staff->waiting($focus),
            'reasons' => $reasons,
            'reasonMax' => max(1, (int) $reasons->max('total')),
            'recent' => $this->staff->recentActivity(),
        ]);
    }

    public function sanctions()
    {
        return view('staff.sanctions', ['accounts' => $this->staff->sanctions()]);
    }

    public function activity()
    {
        return view('staff.activity', ['log' => $this->staff->activity()]);
    }
}
