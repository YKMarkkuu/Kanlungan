@extends('layouts.community')

@section('title', 'Me · Kanlungan')
@section('nav', 'me')

@section('head')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
@endsection

@section('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js" defer></script>
@endsection

@section('content')
    @php($nameValue = $display_name === 'Anonymous' ? '' : $display_name)
    @php($editingName = $errors->has('display_name'))

    <div class="stack cm-profile">
        <header class="cm-header"><h1>Me</h1></header>

        <section class="card cm-identity {{ $editingName ? 'is-editing' : '' }}" aria-label="Profile">
            <form method="POST" action="{{ route('profile.avatar') }}" class="cm-identity__avatar-form"
                  enctype="multipart/form-data" data-avatar-upload>
                @csrf
                <input class="cm-visually-hidden" type="file" id="avatar" name="avatar"
                       accept="image/jpeg,image/png,image/webp" required data-avatar-input>
                <button type="button" class="cm-identity__avatar" data-avatar-choose aria-label="Change profile picture">
                    <x-avatar :avatar-path="$user->avatar_path" :name="$display_name" />
                    <span class="cm-identity__badge" aria-hidden="true">@include('components.icon', ['name' => 'camera'])</span>
                </button>
            </form>

            <div class="cm-identity__who">
                <button type="button"
                        class="cm-identity__name {{ $nameValue === '' ? 'is-placeholder' : '' }}"
                        data-name-edit
                        aria-label="Edit display name. Currently {{ $display_name }}"
                        @if ($editingName) hidden @endif>{{ $display_name }}</button>

                <form method="POST" action="{{ route('community.me.name') }}" class="cm-identity__name-form"
                      data-name-form autocomplete="off" @unless ($editingName) hidden @endunless>
                    @csrf
                    <label class="cm-visually-hidden" for="display_name">Display name</label>
                    <input class="cm-identity__input" type="text" id="display_name" name="display_name"
                           value="{{ old('display_name', $nameValue) }}" data-original="{{ $nameValue }}"
                           maxlength="40" placeholder="Anonymous" enterkeyhint="done">
                </form>

                @error('display_name') <p class="field__error" role="alert">{{ $message }}</p> @enderror
                <p class="cm-identity__hint cm-identity__hint--idle text-small text-muted">Tap your photo or name to change them.</p>
                <p class="cm-identity__hint cm-identity__hint--editing text-small text-muted">Shown only where you turn anonymous off. Leave empty to stay Anonymous.</p>
            </div>
        </section>
        @error('avatar') <p class="field__error" role="alert">{{ $message }}</p> @enderror

        <div class="cm-avatar-crop" id="avatar-crop" role="dialog" aria-modal="true"
             aria-labelledby="avatar-crop-title" hidden data-avatar-crop>
            <button type="button" class="cm-avatar-crop__backdrop" aria-label="Cancel avatar crop" data-avatar-cancel></button>
            <section class="cm-avatar-crop__panel">
                <h2 id="avatar-crop-title">Position your photo</h2>
                <p class="text-small text-muted">Drag to position and pinch to zoom, then save your profile picture.</p>
                <div class="cm-avatar-crop__stage">
                    <img src="" alt="Avatar crop preview" data-avatar-preview>
                </div>
                <p class="field__error" role="alert" aria-live="polite" data-avatar-error hidden></p>
                <div class="cm-avatar-crop__actions">
                    <button type="button" class="btn btn--outline" data-avatar-cancel>Cancel</button>
                    <button type="button" class="btn btn--primary" data-avatar-save>Save Avatar</button>
                </div>
            </section>
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
                <a class="cm-settings-item" href="{{ route('community.me.saved') }}">Saved posts</a>
            </div>
        </section>

        <section class="cm-settings-group" aria-labelledby="settings-about">
            <h2 class="cm-settings-header" id="settings-about">About &amp; Support</h2>
            <div class="cm-settings-list text-small">
                <a class="cm-settings-item" href="{{ route('page.privacy') }}">Privacy notice</a>
                <a class="cm-settings-item" href="{{ route('page.rules') }}">Rules</a>
                <a class="cm-settings-item" href="{{ route('page.help') }}">Help</a>
                <button type="button" class="cm-settings-item" data-theme-toggle aria-pressed="false">Dark Mode <span class="cm-toggle-switch" aria-hidden="true"></span></button>
                <button type="button" class="cm-settings-item" data-restart-onboarding>How Kanlungan works</button>
            </div>
        </section>

        <section class="cm-settings-group" aria-labelledby="settings-blocked">
            <h2 class="cm-settings-header" id="settings-blocked">Blocked Users</h2>
            <div class="cm-settings-list text-small">
                @forelse ($blockedUsers as $blockedUser)
                    <div class="cm-settings-item cm-settings-item--row">
                        <span>Blocked on {{ $blockedUser->created_at->format('M j, Y') }}</span>
                        <form method="POST" action="{{ route('block.destroy', $blockedUser->block_id) }}" class="cm-settings-item-form">
                            @csrf
                            <button type="submit" class="cm-settings-unblock">Unblock</button>
                        </form>
                    </div>
                @empty
                    <div class="cm-settings-item text-muted" style="text-align: center; padding: 16px;">
                        You haven't blocked anyone.
                    </div>
                @endforelse
            </div>
        </section>
        <div class="text-small text-muted">Language: English</div>

        <h2>Points history</h2>
        @php($visibleHistoryRows = 0)
        @forelse ($history as $group)
            @php($rowsToShow = array_slice($group['rows'], 0, max(0, 5 - $visibleHistoryRows)))
            @php($visibleHistoryRows += count($rowsToShow))
            @if ($rowsToShow !== [])
                <section class="cm-ledger" aria-label="{{ $group['title'] }}">
                    <h3 class="cm-ledger__title">{{ $group['title'] }}</h3>
                    <ul class="cm-ledger__list">
                        @foreach ($rowsToShow as $row)
                            @include('components.point-history-row', ['row' => $row])
                        @endforeach
                    </ul>
                </section>
            @endif
        @empty
            <div class="card cm-empty"><p class="text-muted">No points yet.</p></div>
        @endforelse
        <a href="{{ route('community.me.points') }}" class="cm-settings-item">View full history</a>

        <section class="cm-settings-group cm-settings-group--account" aria-labelledby="settings-account">
            <h2 class="cm-settings-header" id="settings-account">Account</h2>
            <div class="cm-settings-list text-small">
                <form method="POST" action="{{ route('auth.logout') }}" class="cm-settings-item-form">
                    @csrf
                    <button type="submit" class="cm-settings-item cm-settings-item--danger">Log out</button>
                </form>
            </div>
        </section>

        <section class="cm-settings-group cm-danger-zone" aria-labelledby="settings-danger">
            <h2 class="cm-settings-header cm-danger-zone__title" id="settings-danger">Danger Zone</h2>
            <div class="card cm-danger-zone__card stack">
                @if (in_array($role, ['moderator', 'guidance', 'admin', 'dpo'], true))
                    <p class="text-small">Staff accounts cannot be deleted here. Ask an admin to remove your role first.</p>
                @else
                    <p class="text-small">Deleting your account erases your name, email, Student ID, picture, saved posts and the text and photos of everything you posted. Other students' replies stay. This cannot be undone.</p>
                    <button type="button" class="btn btn--danger btn--block" data-delete-open aria-haspopup="dialog">Delete my account</button>
                @endif
                @error('report') <p class="field__error" role="alert">{{ $message }}</p> @enderror
            </div>
        </section>

        @unless (in_array($role, ['moderator', 'guidance', 'admin', 'dpo'], true))
            <div class="cm-modal" id="delete-account-modal" role="dialog" aria-modal="true"
                 aria-labelledby="delete-account-title" data-delete-modal @unless ($errors->has('confirm')) hidden @endunless>
                <button type="button" class="cm-modal__backdrop" aria-label="Cancel account deletion" data-delete-cancel></button>
                <form method="POST" action="{{ route('community.me.delete') }}" class="cm-modal__panel stack" autocomplete="off">
                    @csrf
                    <h2 id="delete-account-title">Delete your account?</h2>
                    <p class="text-small">Your posts and comments will be erased, your photos deleted, and you will be logged out. This cannot be undone.</p>
                    <div class="field">
                        <label class="field__label" for="delete-confirm">Type DELETE to confirm</label>
                        <input class="input" type="text" id="delete-confirm" name="confirm" data-delete-input
                               autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="DELETE">
                        @error('confirm') <p class="field__error">{{ $message }}</p> @enderror
                    </div>
                    <div class="cm-modal__actions">
                        <button type="button" class="btn btn--outline" data-delete-cancel>Cancel</button>
                        <button type="submit" class="btn btn--danger" data-delete-submit disabled>Delete forever</button>
                    </div>
                </form>
            </div>
        @endunless

    </div>
@endsection
