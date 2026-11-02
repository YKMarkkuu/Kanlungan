@extends(auth()->check() ? 'layouts.community' : 'layouts.guest')

@section('back', 'history')
@section('title', 'Community rules · Kanlungan')
@section('nav', 'me')

@php($quorum = \App\Services\ReportService::QUORUM)
@php($minVotes = \App\Services\ReportService::MIN_VOTES)
@php($hours = \App\Services\ReportService::VOTING_HOURS)
@php($guidanceVotes = \App\Services\ReportService::GUIDANCE_AGREE_VOTES)
@php($muteDays = \App\Services\ModerationService::MUTE_DAYS)

@section('content')
    <div class="stack">
        <header class="cm-header"><h1>Rules</h1></header>

        @guest
            <p class="text-small"><a href="{{ route('register') }}">Back to registration</a> · <a href="{{ route('login') }}">Sign in</a></p>
        @endguest

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
            <p class="text-small">Tap the flag on a post or comment and pick a reason: {{ implode(', ', $reasons) }}. Nobody can see who reported.</p>

            <h3>Safety reports</h3>
            <p class="text-small">Reports for <strong>Self-harm</strong> or <strong>Threats</strong> are never put to a vote. They go straight to the moderators, and Guidance may be told. If a post is removed for Self-harm, the author does not lose points. They get a message with people they can talk to.</p>

            <h3>All other reports</h3>
            <p class="text-small">These appear under Activity, in the Community Verdicts tab. Students other than the author and the reporter vote: <strong>Agree</strong> (it breaks the rules), <strong>False report</strong> (no violation) or <strong>Wrong tag</strong> (it breaks a different rule). The vote closes after {{ $quorum }} votes or {{ $hours }} hours, and at least {{ $minVotes }} votes are needed for a result.</p>
            <ul class="rules-list text-small">
                <li>Agree wins: the post or comment is removed. If {{ $guidanceVotes }} or more students agreed, Guidance is also told.</li>
                <li>Wrong tag wins: it is still removed, and moderators check the reason.</li>
                <li>False report wins: the post stays, and moderators review the report.</li>
                <li>A tie or too few votes: nothing happens and the post stays.</li>
            </ul>
            <p class="text-small">Moderators can also warn, mute ({{ $muteDays }} days) or ban an account. Every moderator action needs a written reason and is logged.</p>
        </div>

        <div class="card stack">
            <h2>Points and badges</h2>
            <p class="text-small">Points reward helpfulness, not popularity. You earn {{ \App\Services\PointService::HELPFUL_RECEIVED }} points when your comment is marked <strong>Helpful</strong> and {{ \App\Services\PointService::ACCEPTED_RECEIVED }} points when the post's author accepts it as the answer, but only on <strong>Ask</strong>, <strong>Help</strong> and <strong>Academic</strong> posts. Helpful points are capped at {{ \App\Services\PointService::HELPFUL_DAILY_CAP }} per day.</p>
            <p class="text-small"><strong>Likes never give points.</strong> A report the community agrees with earns you {{ \App\Services\PointService::REPORT_UPHELD_REPORTER }} points. Content removed after a community vote costs {{ abs(\App\Services\PointService::REPORT_UPHELD_AUTHOR) }} points, and a report judged false costs {{ abs(\App\Services\PointService::FALSE_REPORT) }}.</p>
            <p class="text-small">Badges: Newcomer, Helper ({{ \App\Services\PointService::BADGES['Helper'] }} points), Trusted ({{ \App\Services\PointService::BADGES['Trusted'] }} points). There is no public leaderboard.</p>
        </div>

        <div class="card stack">
            <h2>Your account</h2>
            <p class="text-small">You can delete your account at any time from Me, under Danger Zone. Read the <a href="{{ route('page.privacy') }}">privacy notice</a> to see exactly what that erases.</p>
        </div>

        <p class="text-small text-muted">Version {{ config('kanlungan.terms_version') }}</p>
    </div>
@endsection
