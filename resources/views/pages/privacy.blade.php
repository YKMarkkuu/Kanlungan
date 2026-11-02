@extends(auth()->check() ? 'layouts.community' : 'layouts.guest')

@section('back', 'history')
@section('title', 'Privacy notice · Kanlungan')
@section('nav', 'me')

@section('content')
    <div class="stack">
        <header class="cm-header"><h1>Privacy notice</h1></header>

        @guest
            <p class="text-small"><a href="{{ route('register') }}">Back to registration</a> · <a href="{{ route('login') }}">Sign in</a></p>
        @endguest

        <div class="card stack">
            <h2>What we collect</h2>
            <ul class="rules-list text-small">
                <li>To sign up we collect your OLFU student email, your Student ID and your campus. You sign in with a one-time code sent to your email. No password is stored.</li>
                <li>A display name and a profile picture are optional.</li>
                <li>We record the date and the version of these terms when you accept them (current version: {{ config('kanlungan.terms_version') }}).</li>
            </ul>
        </div>

        <div class="card stack">
            <h2>Anonymity and who can see what</h2>
            <ul class="rules-list text-small">
                <li>On an anonymous post or comment, other students only see an alias that is different in every thread.</li>
                <li>The system always records which account wrote a post or comment, so abuse can be handled.</li>
                <li>Moderators can warn, mute or ban the account behind a reported post or comment. They do not see your email or Student ID, but they may see your display name if you set one.</li>
                <li>Only an admin or the data protection officer can unmask an account. Every unmask needs a reason and is logged.</li>
                <li>Nobody can see who reported a post, and your votes on reports are not shown to other students. Your saved posts and blocked list are private to you.</li>
                <li>Photos are re-saved without location or camera data.</li>
                <li>If a report suggests someone may be in danger (Self-harm or Threats), it goes straight to the moderators, and Guidance may be told.</li>
            </ul>
        </div>

        <div class="card stack">
            <h2>Deleting your account</h2>
            <p class="text-small">You can delete your account yourself at any time: open Me, go to Danger Zone and type DELETE. This cannot be undone.</p>
            <ul class="rules-list text-small">
                <li>We erase your name, email, Student ID, display name and profile picture, plus your saved posts, blocked list, notifications and points history.</li>
                <li>The text of all your posts and comments is erased and your photos are deleted from our server. Other students' replies stay, under a "removed by its author" notice.</li>
                <li>Reactions, votes and reports you made may remain, with no link to who you are.</li>
                <li>Moderation records (the action, the reason and the date) are kept as an audit log.</li>
                <li>If your post or comment was under a Self-harm or Threats report when you delete, the moderators are told first. A short excerpt of it (up to {{ \App\Services\AccountDeletionService::EXCERPT_LENGTH }} characters) may stay in their notifications.</li>
                <li>Suspended accounts cannot be deleted until a moderator has handled your appeal. Staff accounts need an admin to remove the role first.</li>
                <li>After deleting, you can register again with the same email as a brand-new account. None of your old content comes back.</li>
            </ul>
        </div>

        <p class="text-small text-muted">Version {{ config('kanlungan.terms_version') }}</p>
    </div>
@endsection
