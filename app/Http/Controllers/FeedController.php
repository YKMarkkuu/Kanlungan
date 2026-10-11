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

        $feed = $community->feed($request->user()->id, $active);

        return view('feed.index', [
            'pinned' => $feed['pinned'],
            'posts' => $feed['posts'],
            'categories' => Post::CATEGORIES,
            'active' => $active,
        ]);
    }
}
