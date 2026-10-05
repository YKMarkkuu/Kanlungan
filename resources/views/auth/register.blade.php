@extends('layouts.guest')

@section('title', 'Create account · Kanlungan')

@section('content')
    <div class="stack--lg stack" id="register">

        <header class="auth-header">
            <div class="logo-circle" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 11.5 12 4l9 7.5" />
                    <path d="M5.5 10v9.5h13V10" />
                    <path d="M10 19.5v-5h4v5" />
                </svg>
            </div>

            <h1>Create account</h1>
            <p class="text-muted">Verify your OLFU student email to join.</p>
        </header>

        <div class="stack--sm stack">
            <div class="steps" aria-hidden="true">
                <span class="steps__item is-active"></span>
                <span class="steps__item"></span>
                <span class="steps__item"></span>
            </div>
            <p class="text-small text-muted" id="step-label" aria-live="polite">Step 1 of 3 · Details</p>
        </div>

        {{-- ================= Step 1 — Details ================= --}}
        <section data-step-panel="1" aria-label="Details">
            {{-- Submit is handled by public/js/register.js (fetch to /auth/send-code). --}}
            <form class="stack" id="details-form" novalidate>
                <div class="field">
                    <label class="field__label" for="student_id">Student ID</label>
                    <input
                        class="input"
                        type="text"
                        id="student_id"
                        name="student_id"
                        placeholder="11-digit Student ID"
                        autocomplete="off"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="11"
                    >
                    <p class="field__hint">11 digits, numbers only.</p>
                    <p class="field__error" id="student-id-error" aria-live="polite" hidden></p>
                </div>

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

                <div class="field">
                    <label class="field__label" for="campus">Campus</label>
                    <div class="input-wrap">
                        <input
                            class="input input--locked"
                            type="text"
                            id="campus"
                            name="campus"
                            value="Pampanga"
                            readonly
                            aria-readonly="true"
                        >
                        <svg class="input-wrap__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="5" y="11" width="14" height="9" rx="2" />
                            <path d="M8 11V8a4 4 0 0 1 8 0v3" />
                        </svg>
                    </div>
                    <p class="field__hint">Kanlungan is for OLFU Pampanga students only.</p>
                </div>

                <button type="submit" class="btn btn--primary btn--block">Send code</button>
            </form>

            <p class="auth-footer" style="margin-top: 24px;">
                Already registered? <a href="{{ route('login') }}">Sign in</a>
            </p>
        </section>

        {{-- ================= Step 2 — Code ================= --}}
        <section data-step-panel="2" aria-label="Code" hidden>
            {{-- Banner sits outside .stack so its hidden state doesn't add sibling spacing. --}}
            <div class="field__error" id="code-banner" aria-live="polite" style="margin-bottom: 12px;" hidden></div>

            <div class="stack">
                <div class="stack--sm stack">
                    <p>Enter the 6-digit code we emailed you</p>
                    <p class="field__hint" id="code-email-line" hidden>Sent to <span id="code-email"></span></p>
                </div>

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
        </section>

        {{-- ================= Step 3 — Rules ================= --}}
        <section data-step-panel="3" aria-label="Rules" hidden>
            {{-- Banner sits outside .stack--lg so its hidden state doesn't add sibling spacing. --}}
            <div class="field__error" id="rules-banner" aria-live="polite" style="margin-bottom: 12px;" hidden></div>

            <form class="stack--lg stack" id="rules-form" novalidate>
                <div class="stack">
                    <h2>Community rules</h2>

                    <div class="card">
                        <ul class="rules-list">
                            <li>Be respectful. No bullying, harassment, or hate.</li>
                            <li>Do not share anyone's personal information.</li>
                            <li>No spam, ads, or unrelated links.</li>
                            <li>Anonymous does not mean unaccountable. Admins can review reports for safety.</li>
                            <li>Kanlungan is not emergency help. In an emergency, contact local services.</li>
                        </ul>
                    </div>
                </div>

                <label class="check" for="accept">
                    <input type="checkbox" id="accept" name="accept" required>
                    <span>I accept the community rules and privacy notice</span>
                </label>

                <div class="field">
                    <label class="field__label" for="display_name">Display name (optional)</label>
                    <input
                        class="input"
                        type="text"
                        id="display_name"
                        name="display_name"
                        placeholder="Display name"
                        autocomplete="nickname"
                        maxlength="40"
                    >
                    <p class="field__hint">If left blank, you'll appear as an anonymous alias by default.</p>
                    <p class="field__error" id="display-name-error" aria-live="polite" hidden></p>
                </div>

                <button type="submit" class="btn btn--primary btn--block" id="create-btn" disabled>Create account</button>
            </form>
        </section>

    </div>
@endsection

@section('scripts')
    <script src="{{ asset('js/register.js') }}" defer></script>
@endsection
