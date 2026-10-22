@extends('layouts.community')

@section('title', 'Me · Kanlungan')
@section('nav', 'me')

@section('content')
    <div class="stack">
        <header class="cm-header"><h1>Me</h1></header>

        <div class="card stack">
            <div class="cm-me__who">
                <div class="cm-avatar cm-avatar--lg" aria-hidden="true"></div>
                <strong>Display name: {{ $display_name }}</strong>
            </div>

            <details class="cm-edit-name">
                <summary class="link-btn">Edit name</summary>
                <form method="POST" action="{{ route('community.me.name') }}" class="stack">
                    @csrf
                    <label class="text-small text-muted" for="display_name">Shown only on posts and comments where you turn anonymous off. Leave empty to stay Anonymous.</label>
                    <input class="input" type="text" id="display_name" name="display_name" value="{{ $display_name === 'Anonymous' ? '' : $display_name }}" maxlength="40" placeholder="Anonymous">
                    @error('display_name') <p class="field__error">{{ $message }}</p> @enderror
                    <button type="submit" class="btn btn--primary">Save name</button>
                </form>
            </details>
        </div>

        <div class="card cm-points">
            <div class="cm-points__top">
                <strong>{{ $points }} points</strong>
                <span class="cm-badge">{{ $standing['badge'] }}</span>
            </div>
            <div class="cm-meter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $standing['percent'] }}">
                <div class="cm-meter__fill" style="width: {{ $standing['percent'] }}%"></div>
            </div>
            <p class="text-small text-muted">
                @if ($standing['next'])
                    {{ $standing['to_next'] }} more to reach {{ $standing['next'] }}
                @else
                    You reached the top badge.
                @endif
            </p>
        </div>

        <div class="cm-chips" aria-label="Badges">
            @foreach ($standing['badges'] as $name => $earned)
                <span class="chip {{ $earned ? 'chip--active' : '' }}">{{ $name }} {{ $earned ? '✓' : '(locked)' }}</span>
            @endforeach
        </div>

        <div class="card cm-me__links text-small">
            <a href="{{ route('community.me.posts') }}">My posts</a>
            <a href="{{ route('community.me.comments') }}">My comments</a>
            <span class="text-muted">Language: English</span>
            <span><a href="{{ route('page.privacy') }}">Privacy notice</a> · <a href="{{ route('page.rules') }}">Rules</a> · <a href="{{ route('page.help') }}">Help</a></span>
            @if (in_array($role, ['moderator', 'admin', 'guidance'], true))
                <a href="{{ route('moderation') }}">Moderation queue</a>
            @endif
        </div>

        <h2>Points history</h2>
        @forelse ($history as $row)
            <div class="card cm-points__row">
                <span>{{ $row['label'] }} <span class="text-small text-muted">{{ $row['time'] }}</span></span>
                <strong class="{{ $row['amount'] < 0 ? 'cm-points--neg' : 'cm-points--pos' }}">{{ $row['amount'] > 0 ? '+' : '' }}{{ $row['amount'] }}</strong>
            </div>
        @empty
            <div class="card cm-empty"><p class="text-muted">No points yet.</p></div>
        @endforelse

        <div class="card">
            <form method="POST" action="{{ route('auth.logout') }}">
                @csrf
                <button type="submit" class="btn btn--block">Log out</button>
            </form>
        </div>
    </div>
@endsection
