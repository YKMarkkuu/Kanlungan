@extends('layouts.community')

@section('title', 'Me · Kanlungan')
@section('nav', 'me')

@section('content')
    <div class="stack cm-profile">
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

        <section class="cm-settings-group" aria-labelledby="settings-activity">
            <h2 class="cm-settings-header" id="settings-activity">Activity</h2>
            <div class="cm-settings-list text-small">
                <a class="cm-settings-item" href="{{ route('community.me.posts') }}">My posts</a>
                <a class="cm-settings-item" href="{{ route('community.me.comments') }}">My comments</a>
            </div>
        </section>

        <section class="cm-settings-group" aria-labelledby="settings-about">
            <h2 class="cm-settings-header" id="settings-about">About &amp; Support</h2>
            <div class="cm-settings-list text-small">
                <a class="cm-settings-item" href="{{ route('page.privacy') }}">Privacy notice</a>
                <a class="cm-settings-item" href="{{ route('page.rules') }}">Rules</a>
                <a class="cm-settings-item" href="{{ route('page.help') }}">Help</a>
                @if (in_array($role, ['moderator', 'admin', 'guidance'], true))
                    <a class="cm-settings-item" href="{{ route('moderation') }}">Moderation queue</a>
                @endif
            </div>
        </section>

        <section class="cm-settings-group" aria-labelledby="settings-blocked">
            <h2 class="cm-settings-header" id="settings-blocked">Blocked Users</h2>
            <div class="cm-settings-list text-small">
                @forelse ($blockedUsers as $blockedUser)
                    <form method="POST" action="{{ route('block.destroy', $blockedUser->pivot->id) }}" class="cm-settings-item-form">
                        @csrf
                        <button type="submit" class="cm-settings-item cm-settings-item--row">
                            <span>{{ $blockedUser->display_name ?: 'Anonymous account' }}</span>
                            <span class="cm-settings-item--danger">Unblock</span>
                        </button>
                    </form>
                @empty
                    <p class="text-small text-muted">You haven't blocked anyone.</p>
                @endforelse
            </div>
        </section>
        <div class="text-small text-muted">Language: English</div>

        <h2>Points history</h2>
        @forelse ($history as $row)
            <div class="card cm-points__row">
                <span>{{ $row['label'] }} <span class="text-small text-muted">{{ $row['time'] }}</span></span>
                <strong class="{{ $row['amount'] < 0 ? 'cm-points--neg' : 'cm-points--pos' }}">{{ $row['amount'] > 0 ? '+' : '' }}{{ $row['amount'] }}</strong>
            </div>
        @empty
            <div class="card cm-empty"><p class="text-muted">No points yet.</p></div>
        @endforelse

        <section class="cm-settings-group cm-settings-group--account" aria-labelledby="settings-account">
            <h2 class="cm-settings-header" id="settings-account">Account</h2>
            <div class="cm-settings-list text-small">
                <form method="POST" action="{{ route('auth.logout') }}" class="cm-settings-item-form">
                    @csrf
                    <button type="submit" class="cm-settings-item cm-settings-item--danger">Log out</button>
                </form>
            </div>
        </section>

    </div>
@endsection
