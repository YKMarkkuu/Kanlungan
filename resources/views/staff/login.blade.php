@extends('layouts.guest')

@section('title', 'Staff sign-in · Kanlungan')

@section('head')
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="{{ asset('css/staff.css') }}">
@endsection

@section('content')
    <div class="stack stack--lg sf-login">
        <header class="auth-header">
            <div class="logo-circle" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 5 6v5c0 4.5 3 8 7 10 4-2 7-5.5 7-10V6l-7-3Z" /></svg>
            </div>
            <h1>Staff sign-in</h1>
            <p class="text-muted">For Kanlungan staff and DPO accounts. Students sign in on the main page.</p>
        </header>

        @if (session('status'))
            <div class="sf-notice" role="status" aria-live="polite">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="sf-notice sf-notice--error" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        @if ($email)
            <form class="stack" method="POST" action="{{ route('staff.verify') }}">
                @csrf
                <input type="hidden" name="email" value="{{ $email }}">
                <p>Enter the 6-digit code sent to <strong>{{ $email }}</strong>.</p>
                <div class="field">
                    <label class="field__label" for="code">Sign-in code</label>
                    <input class="input code-input-single" id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
                </div>
                <button type="submit" class="btn btn--primary btn--block">Sign in</button>
            </form>

            <div class="code-actions">
                <form method="POST" action="{{ route('staff.send-code') }}">
                    @csrf
                    <input type="hidden" name="email" value="{{ $email }}">
                    <button type="submit" class="link-btn">Send a new code</button>
                </form>
                <a href="{{ route('staff.login') }}" class="link-btn">Use a different email</a>
            </div>
        @else
            <form class="stack" method="POST" action="{{ route('staff.send-code') }}">
                @csrf
                <div class="field">
                    <label class="field__label" for="email">Staff email</label>
                    <input class="input" id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" required autofocus>
                    <p class="field__hint">Staff accounts are created by an admin. You will get a code by email.</p>
                </div>
                <button type="submit" class="btn btn--primary btn--block">Send code</button>
            </form>
        @endif

        @if ($devLogin)
            <form class="stack" method="POST" action="{{ route('staff.dev-login') }}">
                @csrf
                <p class="divider">Local testing only</p>
                <div class="field">
                    <label class="field__label" for="dev_email">Email</label>
                    <input class="input" id="dev_email" name="email" type="email" value="{{ old('email') }}" required>
                </div>
                <div class="field">
                    <label class="field__label" for="dev_password">Password</label>
                    <input class="input" id="dev_password" name="password" type="password" required>
                </div>
                <button type="submit" class="btn btn--outline btn--block">Sign in with password</button>
                <p class="field__hint">Only shown when APP_ENV=local. Create the placeholder accounts with: php artisan db:seed --class=StaffDemoSeeder</p>
            </form>
        @endif

        <p class="auth-footer"><a href="{{ route('login') }}">Student sign-in</a></p>
    </div>
@endsection
