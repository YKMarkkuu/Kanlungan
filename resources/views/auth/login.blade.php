@extends('layouts.guest')

@section('title', 'Sign in · Kanlungan')

@section('content')
    <div class="stack--lg stack" id="login">

        <header class="auth-header">
            {{-- Placeholder logo. Swap this block for the real logo later. --}}
            <div class="logo-circle" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 11.5 12 4l9 7.5" />
                    <path d="M5.5 10v9.5h13V10" />
                    <path d="M10 19.5v-5h4v5" />
                </svg>
            </div>

            <h1>Sign in to Kanlungan</h1>
            <p class="text-muted">Enter your OLFU Pampanga email to receive a sign-in code.</p>
        </header>

        {{-- TODO: move .auth-message to components.css --}}
        @if (session('status'))
            <div class="card auth-message" role="status" aria-live="polite" style="border-color: var(--accent); background-color: var(--accent-soft); color: var(--accent-dark);">{{ session('status') }}</div>
        @endif

        {{-- Submit is handled by public/js/login.js (fetch to /auth/send-code, then /auth/login). --}}
        <form class="stack" id="login-form" novalidate>
            <div class="field">
                <label class="field__label" for="email">OLFU email</label>
                <input
                    class="input"
                    type="email"
                    id="email"
                    name="email"
                    placeholder="OLFU email"
                    autocomplete="email"
                    aria-describedby="email-hint"
                >
                <p class="field__hint" id="email-hint" aria-live="polite" hidden></p>
            </div>

            {{-- Rate limit, network, and code-state errors. --}}
            <div class="field__error" id="login-banner" aria-live="polite" hidden></div>

            {{-- Revealed by login.js after the code is sent. --}}
            <div class="stack" id="code-block" hidden>
                <p>Enter the 6-digit code we emailed you</p>

                <div class="code-inputs" role="group" aria-label="6-digit code">
                    <input class="input code-input" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" aria-label="Digit 1 of 6">
                    <input class="input code-input" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="off" aria-label="Digit 2 of 6">
                    <input class="input code-input" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="off" aria-label="Digit 3 of 6">
                    <input class="input code-input" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="off" aria-label="Digit 4 of 6">
                    <input class="input code-input" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="off" aria-label="Digit 5 of 6">
                    <input class="input code-input" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="off" aria-label="Digit 6 of 6">
                </div>

                <div class="code-actions">
                    <button type="button" class="link-btn" id="resend-btn" disabled>Resend code</button>
                    <button type="button" class="link-btn" id="change-email-btn">Change email</button>
                </div>
            </div>

            <button type="submit" class="btn btn--outline btn--block" id="send-btn">Send code</button>
        </form>

        <p class="auth-footer">
            No account yet? <a href="{{ route('register') }}">Register</a>
        </p>

        <p class="auth-disclaimer">
            Your name stays hidden unless you choose to show it.
        </p>

    </div>
@endsection

@section('scripts')
    <script src="{{ asset('js/login.js') }}" defer></script>
@endsection
