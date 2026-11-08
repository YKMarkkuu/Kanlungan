@extends('layouts.guest')

@section('title', 'Welcome · Kanlungan')
@section('body-class', 'auth-page')
@section('guest-class', 'guest-page--auth')

@section('head')
    <noscript>
        <style>
            .auth-cta-wrap { display: none; }
            .auth-reveal { grid-template-rows: 1fr; opacity: 1; visibility: visible; }
        </style>
    </noscript>
@endsection

@section('content')
    <div class="auth-shell" id="login">
        <header class="auth-brand auth-rise" style="--auth-delay: 40ms">
            <span class="auth-mark"><x-logo /></span>
            <span class="auth-wordmark">Kanlungan</span>
        </header>

        <section class="auth-hero" aria-labelledby="auth-title">
            <h1 class="auth-title auth-rise" id="auth-title" style="--auth-delay: 140ms">A <span class="auth-title__accent">safe space</span> for OLFU Pampanga.</h1>
            <p class="auth-lede auth-rise" style="--auth-delay: 240ms">Verified, anonymous-optional, and built for our campus community. No noise. Just support.</p>
        </section>

        @if (session('status'))
            <div class="auth-notice auth-rise" role="status" aria-live="polite" style="--auth-delay: 300ms">{{ session('status') }}</div>
        @endif

        <section class="auth-card auth-rise" id="auth-card" aria-label="Sign in" style="--auth-delay: 340ms">
            <div class="auth-collapse auth-cta-wrap">
                <div class="auth-collapse__clip">
                    <div class="auth-pad">
                        <button type="button" class="btn btn--primary btn--block auth-cta" id="auth-open" aria-expanded="false" aria-controls="auth-reveal">
                            <span>Sign in or Register</span>
                            <span class="auth-cta__arrow" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 12h14M13 6l6 6-6 6" />
                                </svg>
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="auth-collapse auth-reveal" id="auth-reveal">
                <div class="auth-collapse__clip">
                    <div class="auth-pad stack">
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

                            <div class="field__error" id="login-banner" aria-live="polite" hidden></div>

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

                            <button type="submit" class="btn btn--primary btn--block" id="send-btn">Send code</button>
                        </form>

                        <p class="auth-card__alt text-small">New here? <a href="{{ route('register') }}">Create your account</a></p>
                    </div>
                </div>
            </div>
        </section>

        <footer class="auth-foot auth-rise" style="--auth-delay: 420ms">
            <p>Only for students with an @@student.fatima.edu.ph email.</p>
            <p><a href="{{ route('page.rules') }}">Community rules</a> · <a href="{{ route('page.privacy') }}">Privacy notice</a></p>
        </footer>
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('js/login.js') }}" defer></script>
    <script src="{{ asset('js/landing.js') }}" defer></script>
@endsection
