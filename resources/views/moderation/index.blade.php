@extends('layouts.community')

@section('title', 'Moderation · Kanlungan')
@section('nav', 'me')

@php
    $labels = ['warn' => 'Warn', 'remove' => 'Remove', 'restore' => 'Restore', 'mute' => 'Mute', 'ban' => 'Ban', 'unmute' => 'Unmute', 'unban' => 'Unban', 'dismiss' => 'No action', 'refer' => 'Refer to Guidance', 'handled' => 'Mark handled'];
@endphp

@section('content')
    <div class="stack">
        <header class="cm-header">
            <h1>Moderation</h1>
            <nav class="cm-mod-tabs text-small" aria-label="Moderation">
                @if ($role !== 'guidance')
                    <a href="{{ route('moderation', ['tab' => 'queue']) }}" class="{{ $tab === 'queue' ? 'is-active' : '' }}">Queue</a>
                @endif
                @if (in_array($role, ['guidance', 'admin'], true))
                    <a href="{{ route('moderation', ['tab' => 'guidance']) }}" class="{{ $tab === 'guidance' ? 'is-active' : '' }}">Guidance</a>
                @endif
                @if ($role !== 'guidance')
                    <a href="{{ route('moderation', ['tab' => 'history']) }}" class="{{ $tab === 'history' ? 'is-active' : '' }}">History</a>
                @endif
            </nav>
        </header>

        @if ($tab === 'history')
            @forelse ($history as $row)
                <div class="card stack">
                    <p class="text-small"><strong>{{ $labels[$row['action']] ?? $row['action'] }}</strong>@if ($row['category']) · {{ $row['category'] }}@endif · {{ $row['moderator'] }} · {{ $row['time'] }}</p>
                    <p class="text-small text-muted">Reason: {{ $row['reason'] }}</p>
                </div>
            @empty
                <div class="card cm-empty"><p class="text-muted">No actions yet.</p></div>
            @endforelse
        @else
            <nav class="cm-chips" aria-label="Filter by severity">
                <a href="{{ route('moderation', ['tab' => $tab]) }}" class="chip {{ $severity === null ? 'chip--active' : '' }}">All</a>
                @foreach (['critical' => 'Critical', 'high' => 'High', 'medium' => 'Medium'] as $key => $label)
                    <a href="{{ route('moderation', ['tab' => $tab, 'severity' => $key]) }}" class="chip {{ $severity === $key ? 'chip--active' : '' }}">{{ $label }}</a>
                @endforeach
            </nav>

            @forelse ($cards as $card)
                <article class="card cm-mod cm-mod--{{ $card['severity'] }}">
                    <div class="cm-mod__head">
                        <span class="cm-sev cm-sev--{{ $card['severity'] }}">{{ ucfirst($card['severity']) }}</span>
                        <span class="text-small text-muted">{{ $card['category'] }} · {{ $card['agree'] }} agree of {{ $card['votes'] }} votes</span>
                    </div>

                    @if ($card['subject'] === 'reporter')
                        <p class="text-small"><strong>False report.</strong> The community said this report was false. Judging the reporter: {{ $card['subject_user'] }}.</p>
                    @else
                        <p class="text-small text-muted">Posted by {{ $card['subject_user'] }}</p>
                    @endif

                    <p class="cm-post__body">{{ $card['body'] }}</p>
                    @if ($card['details'])
                        <p class="text-small text-muted">Reporter's note: {{ $card['details'] }}</p>
                    @endif

                    <p class="text-small text-muted">
                        {{ ucfirst($card['kind']) }}
                        @if ($card['removed']) · removed @endif
                        @if ($card['status'] !== 'escalated')
                            @if ($card['status'] !== 'resolved') · vote still {{ $card['status'] }} @else · vote result: {{ str_replace('_', ' ', $card['outcome']) }} @endif
                        @endif
                        @if ($card['referred']) · referred to Guidance @endif
                        · {{ $card['time'] }}
                    </p>

                    @if ($card['actions'])
                        <form method="POST" action="{{ route('moderation.act', $card['id']) }}" class="stack">
                            @csrf
                            <input class="input" type="text" name="reason" placeholder="Reason (required, saved in the log)" maxlength="500" required>
                            <label class="text-small" for="override-category-{{ $card['id'] }}">Override Category (if reporter chose wrong)</label>
                            <select class="input" id="override-category-{{ $card['id'] }}" name="override_category">
                                @foreach ($categories as $category)
                                    <option value="{{ $category }}" @selected($category === $card['category'])>{{ $category }}</option>
                                @endforeach
                            </select>
                            <div class="cm-mod__actions">
                                @foreach ($card['actions'] as $action)
                                    <button type="submit" name="action" value="{{ $action }}" class="btn {{ $action === 'refer' ? 'cm-btn-danger' : '' }}">{{ $labels[$action] }}</button>
                                @endforeach
                            </div>
                        </form>
                    @endif
                </article>
            @empty
                <div class="card cm-empty"><p class="text-muted">Nothing waiting. Nice.</p></div>
            @endforelse

            <p class="text-small text-muted">Every action asks for a reason and is logged.</p>
        @endif
    </div>
@endsection
