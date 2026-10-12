<?php

namespace App\Http\Controllers;

use App\Models\PointLog;
use App\Services\CommunityService;
use App\Services\PointService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        $history = PointLog::where('user_id', $user->id)
            ->latest()->latest('id')->limit(30)->get()
            ->map(fn (PointLog $log) => [
                'amount' => $log->amount,
                'label' => PointService::LABELS[$log->reason] ?? $log->reason,
                'time' => $log->created_at?->diffForHumans(),
            ]);

        $points = (int) ($user->points ?? 0);

        return view('profile.show', [
            'role' => (string) $user->role,
            'display_name' => trim((string) $user->display_name) !== '' ? $user->display_name : 'Anonymous',
            'points' => $points,
            'standing' => PointService::standing($points),
            'history' => $history,
        ]);
    }

    public function updateName(Request $request): RedirectResponse
    {
        $data = $request->validate(['display_name' => ['nullable', 'string', 'min:2', 'max:40']]);

        $name = isset($data['display_name']) ? trim($data['display_name']) : '';

        \App\Models\User::whereKey($request->user()->id)->update(['display_name' => $name === '' ? null : $name]);

        return back()->with('report_status', 'Display name saved.');
    }

    public function posts(Request $request, CommunityService $community)
    {
        return view('profile.posts', ['posts' => $community->myPosts($request->user()->id)]);
    }

    public function comments(Request $request, CommunityService $community)
    {
        return view('profile.comments', ['comments' => $community->myComments($request->user()->id)]);
    }
}
