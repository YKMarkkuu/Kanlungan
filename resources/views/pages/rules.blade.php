@extends('layouts.community')

@section('back', 'history')
@section('title', 'Community rules · Kanlungan')
@section('nav', 'me')

@section('content')
    <div class="stack">
        <header class="cm-header"><h1>Rules</h1></header>

        <div class="card stack">
            <h2>Community rules</h2>
            <ul class="rules-list">
                <li>Be respectful. No bullying, harassment, or hate.</li>
                <li>Do not share anyone's personal information.</li>
                <li>No spam, ads, or unrelated links.</li>
                <li>Anonymous does not mean unaccountable. Admins can review reports for safety.</li>
                <li>Kanlungan is not emergency help. In an emergency, contact local services.</li>
            </ul>
        </div>

        <div class="card stack">
            <h2>How reports work</h2>
            <p class="text-small">Tap the flag to report a post or comment and pick a reason: {{ implode(', ', $reasons) }}. Nobody can see who reported.</p>
            <p class="text-small">The reported item glows red and moves to the top of everyone's feed. Other students vote: <strong>Agree</strong> (it breaks the rules), <strong>False report</strong> (no violation), or <strong>Wrong tag</strong> (it breaks a different rule).</p>
            <ul class="rules-list text-small">
                <li>Agree wins: the post or comment is removed. If almost everyone agrees, Guidance is also told.</li>
                <li>Wrong tag wins: it is still removed, and moderators check the reason.</li>
                <li>False report wins: the red glow goes away, the post stays, and moderators review the report.</li>
            </ul>
        </div>

        <div class="card stack">
            <h2>Points and badges</h2>
            <p class="text-small">You earn points when students like your post or comment, mark it helpful, or accept it as the answer. Removed content and false reports cost points. Badges: Newcomer, Helper (50 points), Trusted (200 points). There is no public leaderboard.</p>
        </div>
    </div>
@endsection
