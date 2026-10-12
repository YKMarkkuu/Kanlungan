<?php

namespace App\Http\Controllers;

use App\Services\CommunityService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request, CommunityService $community)
    {
        $q = trim((string) $request->query('q', ''));

        return view('feed.search', [
            'q' => $q,
            'results' => $community->search($request->user()->id, $q),
        ]);
    }
}
