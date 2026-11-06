<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\CommunityService;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function index(Request $request, CommunityService $community)
    {
        $category = $request->query('category');
        $active = in_array($category, Post::CATEGORIES, true) ? $category : null;

        $cursor = $request->query('feed');
        $feed = $community->feed($request->user()->id, $active, is_string($cursor) ? $cursor : null);
        $posts = $feed['posts']->appends($active !== null ? ['category' => $active] : []);

        return view('feed.index', [
            'hasPendingReports' => $feed['hasPendingReports'],
            'posts' => $posts,
            'categories' => Post::CATEGORIES,
            'active' => $active,
        ]);
    }
}
