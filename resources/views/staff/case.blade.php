@extends('layouts.staff')

@section('title', 'Case '.$case['id'].' · Kanlungan staff')

@php
    use App\Services\StaffDashboardService as Staff;

    $has = fn (string $action) => in_array($action, $case['actions'], true);
    $suggested = $case['suggestion']['action'] ?? null;
    $btn = fn (string $action, bool $danger = false) => 'btn'.($danger ? ' btn--danger' : '').($suggested === $action ? ' is-suggested' : '');
    $roleName = ['admin' => 'Admin', 'guidance' => 'Guidance', 'moderator' => 'Moderator'];
    $stageNote = match (true) {
        $case['status'] === 'escalated' => 'Safety report: it came straight to staff and is not put to a community vote.',
        $case['status'] === 'resolved' && $case['outcome'] === 'moderator_cleared' => 'Closed by staff with no action.',
        $case['status'] === 'resolved' && $case['outcome'] === 'moderator_reviewed' => 'Reviewed and closed by staff.',
        $case['status'] === 'resolved' => 'The community vote ended: '.str_replace('_', ' ', (string) $case['outcome']).'.',
        $case['status'] === 'voting' => 'The community is still voting.',
        default => 'The community vote has not started.',
    };
@endphp

@section('content')
    <a class="sf-back" href="{{ route('staff.reports') }}">@include('components.icon', ['name' => 'back']) Reports</a>

    <header class="sf-head">
        <h1 class="sf-title">{{ $case['about_reporter'] ? 'Possible false report' : $case['category'] }} on a {{ $case['kind'] }}</h1>
        <p class="sf-lede">
            <span class="sf-sev sf-sev--{{ $case['severity'] }}">{{ ucfirst($case['severity']) }}</span>
            reported {{ Staff::when($case['reported_at']) }} (Philippine time). {{ $stageNote }}
        </p>
    </header>

    <div class="sf-case">
        <div>
            <section class="sf-panel {{ $case['severity'] === 'critical' ? 'sf-panel--critical' : '' }}">
                <h2>{{ $case['about_reporter'] ? 'The post that was reported (the community found no violation)' : 'What was posted' }}</h2>

                <p class="sf-byline">
                    <b>{{ $case['author_label'] }}</b>
                    <span class="text-muted">{{ Staff::when($case['posted_at']) }}</span>
                    @if ($case['removed'])
                        <span class="sf-tag">Removed by {{ $case['removed_by'] === 'moderator' ? 'staff' : 'community vote' }}</span>
                    @endif
                </p>

                @if ($case['context'])
                    <p class="sf-context">On the post: {{ $case['context'] }}</p>
                @endif

                <div class="sf-content {{ $case['removed'] ? 'sf-content--removed' : '' }}">{{ $case['body'] }}</div>

                @foreach ($case['images'] as $image)
                    <img class="sf-photo" src="{{ $image }}" alt="Photo {{ $loop->iteration }} attached to the post" loading="lazy">
                @endforeach
            </section>

            <section class="sf-panel">
                <h2>Why it was reported</h2>
                <dl class="sf-facts">
                    <div><dt>Reason</dt><dd>{{ $case['category'] }}</dd></div>
                    <div><dt>Reporter's note</dt><dd>{{ $case['details'] ?: 'No note.' }}</dd></div>
                    <div>
                        <dt>Community vote</dt>
                        <dd>
                            @if ($case['votes'] > 0)
                                {{ $case['agree'] }} agree, {{ $case['false_votes'] }} false report, {{ $case['wrong_tag_votes'] }} wrong tag ({{ $case['votes'] }} voted)
                            @else
                                No votes yet.
                            @endif
                        </dd>
                    </div>
                    <div><dt>Reporter</dt><dd>Hidden. Reporters are never named to protect them.</dd></div>
                    @if ($case['referred'])
                        <div><dt>Guidance</dt><dd>{{ $case['handled'] ? 'Referred and marked handled.' : 'Referred, waiting for Guidance.' }}</dd></div>
                    @endif
                </dl>
            </section>

            <section class="sf-panel">
                <h2>History on this case</h2>
                @if ($case['timeline']->isEmpty())
                    <p class="text-muted">No staff have acted on this yet.</p>
                @else
                    <ul class="sf-timeline">
                        @foreach ($case['timeline'] as $t)
                            <li>
                                <strong>{{ $t['label'] }}</strong>
                                <span class="text-muted">by {{ $t['by'] }}@if ($t['role']) ({{ $roleName[$t['role']] ?? $t['role'] }})@endif, {{ Staff::when($t['at']) }}</span>
                                <br><span>{{ $t['reason'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <aside class="sf-decision" aria-label="Decision">
            <section class="sf-panel">
                <h2>Decision</h2>

                <div class="sf-record">
                    <strong>{{ $case['subject_label'] }}, {{ $case['record']['ref'] ?? 'account unknown' }}</strong>
                    {{ $case['record']['summary'] }}
                    @if ($case['record']['banned']) Currently banned. @endif
                    @if ($case['record']['muted_until']) Muted until {{ Staff::when($case['record']['muted_until']) }}. @endif
                </div>

                @if ($case['subject_deleted'])
                    <div class="sf-advice">
                        <b>Account deleted</b>
                        The student deleted their account. They can no longer be warned, muted or revealed. You can only close the case.
                    </div>
                @endif

                @if ($case['suggestion'])
                    <div class="sf-advice">
                        <b>Suggested: {{ $labels[$case['suggestion']['action']] }}</b>
                        {{ $case['suggestion']['why'] }} You decide.
                    </div>
                @endif

                @if ($case['actions'])
                    <form method="POST" action="{{ route('staff.act', $case['id']) }}" class="sf-form">
                        @csrf
                        {{-- Pressing Enter in the reason box must never pick a real action, so it hits this inert button first. --}}
                        <button type="submit" name="action" value="" class="visually-hidden" tabindex="-1" aria-hidden="true"></button>

                        <div class="field">
                            <label class="field__label" for="reason">Reason (saved in the audit log)</label>
                            <input class="input" id="reason" name="reason" type="text" list="reason-ideas" maxlength="500" required value="{{ old('reason') }}">
                            <datalist id="reason-ideas">
                                <option value="Sexual content in a public post"></option>
                                <option value="Profanity and insults"></option>
                                <option value="Harassing another student"></option>
                                <option value="Repeat offence after an earlier warning"></option>
                                <option value="No rule was broken"></option>
                                <option value="Reported in good faith"></option>
                            </datalist>
                        </div>

                        @if (array_intersect(['warn', 'remove', 'mute', 'ban'], $case['actions']))
                            <div class="field">
                                <label class="field__label" for="override_category">Reason category (change it if the reporter chose wrong)</label>
                                <select class="input sf-select" id="override_category" name="override_category">
                                    @foreach ($categories as $category)
                                        <option value="{{ $category }}" @selected($category === $case['category'])>{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        @if ($has('remove') || $has('restore'))
                            <fieldset class="sf-group" style="border:0;padding:0;margin:0;">
                                <legend>The {{ $case['kind'] }}</legend>
                                <div class="sf-actions">
                                    @if ($has('remove'))<button class="{{ $btn('remove', true) }}" name="action" value="remove" data-confirm="Remove this {{ $case['kind'] }} for everyone?">Remove {{ $case['kind'] }}</button>@endif
                                    @if ($has('restore'))<button class="{{ $btn('restore') }}" name="action" value="restore">Restore {{ $case['kind'] }}</button>@endif
                                </div>
                            </fieldset>
                        @endif

                        <fieldset class="sf-group" style="border:0;padding:0;margin:0;">
                            <legend>The account</legend>
                            <div class="sf-actions">
                                @if ($has('warn'))<button class="{{ $btn('warn') }}" name="action" value="warn">Warn</button>@endif
                                @if ($has('mute'))<button class="{{ $btn('mute') }}" name="action" value="mute">Mute</button>@endif
                                @if ($has('ban'))<button class="{{ $btn('ban', true) }}" name="action" value="ban" data-confirm="Ban this account? They cannot use Kanlungan until a staff member lifts the ban.">Ban</button>@endif
                                @if ($has('unmute'))<button class="{{ $btn('unmute') }}" name="action" value="unmute">Unmute</button>@endif
                                @if ($has('unban'))<button class="{{ $btn('unban') }}" name="action" value="unban">Unban</button>@endif
                            </div>
                            @if ($has('mute'))
                                <div class="field">
                                    <label class="field__label" for="mute_days">If muting, for how long</label>
                                    <select class="input sf-select" id="mute_days" name="mute_days">
                                        @foreach ($muteChoices as $days)
                                            <option value="{{ $days }}" @selected($days === \App\Services\ModerationService::MUTE_DAYS)>{{ $days }} {{ $days === 1 ? 'day' : 'days' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        </fieldset>

                        @if ($has('dismiss') || $has('refer') || $has('handled'))
                            <fieldset class="sf-group" style="border:0;padding:0;margin:0;">
                                <legend>The case</legend>
                                <div class="sf-actions">
                                    @if ($has('dismiss'))<button class="{{ $btn('dismiss') }}" name="action" value="dismiss">No action, close</button>@endif
                                    @if ($has('refer'))<button class="{{ $btn('refer') }}" name="action" value="refer">Refer to Guidance</button>@endif
                                    @if ($has('handled'))<button class="{{ $btn('handled') }}" name="action" value="handled">Mark handled</button>@endif
                                </div>
                            </fieldset>
                        @endif
                    </form>
                @else
                    <p class="text-muted" style="margin-top: 16px;">There is nothing for you to do on this case.</p>
                @endif

                @if ($case['revealed'])
                    <div class="sf-identity">
                        <b>Revealed to you</b>
                        <dl>
                            <div><dt>Name</dt><dd>{{ $case['revealed']['name'] }}</dd></div>
                            <div><dt>School email</dt><dd>{{ $case['revealed']['email'] }}</dd></div>
                            <div><dt>Student ID</dt><dd>{{ $case['revealed']['student_id'] ?: 'Not on file' }}</dd></div>
                        </dl>
                        <p class="text-small text-muted">Visible to you until {{ Staff::when($case['revealed']['until']) }}. Reveal it again later and you will be asked for a reason again. Keep this private.</p>
                    </div>
                @elseif ($case['can_reveal'])
                    <details class="sf-reveal">
                        <summary>@include('components.icon', ['name' => 'eye']) Reveal who this is</summary>
                        <form method="POST" action="{{ route('staff.reveal', $case['id']) }}" class="stack">
                            @csrf
                            <p class="text-small text-muted">Only when you have to reach the student, for example a safety concern or a school discipline process. Your name and reason are saved in the audit log.</p>
                            <div class="field">
                                <label class="field__label" for="reveal_reason">Why do you need to know?</label>
                                <input class="input" id="reveal_reason" name="reason" type="text" minlength="10" maxlength="500" required>
                            </div>
                            <button type="submit" class="btn btn--outline">Reveal identity</button>
                        </form>
                    </details>
                @else
                    <p class="text-small text-muted" style="margin-top: 16px;">Only admin and guidance accounts can reveal who is behind a report.</p>
                @endif
            </section>
        </aside>
    </div>
@endsection
