@extends('layouts.community')

@section('back', 'history')
@section('title', 'Privacy notice · Kanlungan')
@section('nav', 'me')

@section('content')
    <div class="stack">
        <header class="cm-header"><h1>Privacy notice</h1></header>

        <div class="card stack">
            <h2>Privacy notice</h2>
            <p class="text-small text-muted">Draft summary. It must be reviewed and approved by OLFU (for example the data protection officer) before launch.</p>
            <ul class="rules-list text-small">
                <li>You sign in with your school account so the community stays accountable.</li>
                <li>Other students never see who you are on an anonymous post or comment. They only see an alias that is different in every thread.</li>
                <li>The system always records which account wrote a post or comment. Only an admin can unmask it, and the reason is logged.</li>
                <li>Moderators see the real account behind a reported post or comment so they can warn, mute or ban.</li>
                <li>If a report suggests someone may be in danger, Guidance can be told.</li>
                <li>Photos are re-saved without location or camera data.</li>
                <li>Nobody can see who reported a post, and your votes on reports are not shown to other students.</li>
            </ul>
        </div>
    </div>
@endsection
