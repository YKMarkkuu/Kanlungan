<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Services\CommunityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request, CommunityService $community)
    {
        $alerts = Alert::where('user_id', $request->user()->id)->latest()->latest('id')->limit(50)->get()
            ->map(fn (Alert $a) => [
                'id' => $a->id,
                'kind' => $a->kind,
                'title' => $a->title,
                'body' => $a->body,
                'unread' => $a->read_at === null,
                'time' => $a->created_at?->diffForHumans(null, \Carbon\CarbonInterface::DIFF_ABSOLUTE, true),
            ]);

        return view('alerts.index', [
            'alerts' => $alerts,
            'reportedItems' => $community->reportsForVoting((int) $request->user()->id),
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
}
