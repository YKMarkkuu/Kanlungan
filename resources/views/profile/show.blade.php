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
    <div class="stack cm-profile">
        <header class="cm-header"><h1>Me</h1></header>

        <section class="card cm-profile-header" aria-label="Profile">
            <x-avatar :avatar-path="$user->avatar_path" :name="$display_name" class="cm-avatar--xl" />
            <div class="cm-profile-header__details">
                <h2>{{ $display_name }}</h2>
                <button type="button" class="btn btn--outline" data-profile-edit-toggle
                        aria-expanded="{{ $errors->has('display_name') ? 'true' : 'false' }}"
                        aria-controls="profile-name-form">Edit</button>
            </div>
        </section>

        <section class="card cm-profile-settings stack" aria-labelledby="profile-settings-title">
            <h2 id="profile-settings-title">Profile Settings</h2>
            <form method="POST" action="{{ route('profile.avatar') }}" class="cm-avatar-upload"
                  enctype="multipart/form-data" data-avatar-upload>
                @csrf
                <input class="cm-visually-hidden" type="file" id="avatar" name="avatar"
                       accept="image/jpeg,image/png,image/webp" required data-avatar-input>
                @error('avatar') <p class="field__error">{{ $message }}</p> @enderror
                <button type="button" class="btn btn--outline" data-avatar-choose>Change Avatar</button>
            </form>

            <form method="POST" action="{{ route('community.me.name') }}"
                  class="stack cm-profile-name-form" id="profile-name-form"
                  @if (! $errors->has('display_name')) hidden @endif>
                @csrf
                <label class="text-small text-muted" for="display_name">Shown only on posts and comments where you turn anonymous off. Leave empty to stay Anonymous.</label>
                <input class="input" type="text" id="display_name" name="display_name" value="{{ old('display_name', $display_name === 'Anonymous' ? '' : $display_name) }}" maxlength="40" placeholder="Anonymous">
                @error('display_name') <p class="field__error">{{ $message }}</p> @enderror
                <button type="submit" class="btn btn--primary">Save name</button>
            </form>
        </section>

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
                            <span>Blocked on {{ $blockedUser->pivot->created_at->format('M j, Y') }}</span>
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
