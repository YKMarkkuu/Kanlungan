<?php

namespace App\Http\Controllers;

use App\Models\PointLog;
use App\Services\AccountDeletionService;
use App\Services\CommunityService;
use App\Services\ImageService;
use App\Services\PointService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        $history = PointService::groupHistory(
            PointLog::where('user_id', $user->id)->latest()->latest('id')->limit(30)->get()
        );

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

    public function destroyAccount(Request $request, AccountDeletionService $deletion): RedirectResponse
    {
        $request->validate(
            ['confirm' => ['required', Rule::in(['DELETE'])]],
            [
                'confirm.required' => 'Type DELETE to confirm.',
                'confirm.in' => 'Type DELETE in capital letters to confirm.',
            ],
        );

        $deletion->delete($request->user());

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', 'Your account was deleted. Thank you for being part of Kanlungan.');
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
