<?php

namespace App\Http\Controllers;

use App\Models\PointLog;
use App\Services\CommunityService;
use App\Services\ImageService;
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
            'user' => $user,
            'role' => (string) $user->role,
            'display_name' => trim((string) $user->display_name) !== '' ? $user->display_name : 'Anonymous',
            'points' => $points,
            'standing' => PointService::standing($points),
            'history' => $history,
            'blockedUsers' => $user->blockedUsers()->orderByPivot('created_at', 'desc')->get(),
        ]);
    }

    public function updateAvatar(Request $request, ImageService $images): RedirectResponse
    {
        $data = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $user = $request->user();
        $oldAvatarPath = $user->avatar_path;
        $newAvatarPath = $images->storeAvatar($data['avatar']);

        $user->forceFill(['avatar_path' => $newAvatarPath])->save();
        $images->deleteAvatar($oldAvatarPath);

        return back()->with('report_status', 'Profile picture updated.');
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

    public function saved(Request $request, CommunityService $community)
    {
        return view('profile.saved', ['posts' => $community->bookmarkedPosts((int) $request->user()->id)]);
    }
}
