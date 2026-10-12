<?php

namespace App\Http\Controllers;

use App\Models\Report;

/** Static pages: rules, privacy notice, help (Guidance). */
class PageController extends Controller
{
    public function rules()
    {
        return view('pages.rules', ['reasons' => Report::CATEGORIES]);
    }

    public function privacy()
    {
        return view('pages.privacy');
    }

    public function help()
    {
        return view('pages.help');
    }
}
