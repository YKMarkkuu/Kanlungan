@extends('layouts.guest')

@section('title', 'Sign in · Kanlungan')

@section('content')
    <div class="stack--lg stack">

        <header class="auth-header">
            {{-- Placeholder logo. Swap this block for the real logo later. --}}
            <div class="logo-circle" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 11.5 12 4l9 7.5" />
                    <path d="M5.5 10v9.5h13V10" />
                    <path d="M10 19.5v-5h4v5" />
                </svg>
            </div>

            <h1>OLFU Pampanga Community</h1>
            <p class="text-muted">Ask, share, and help each other</p>
        </header>

        <button type="button" class="btn btn--primary btn--block">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 3 4 6v6c0 4.5 3.2 7.8 8 9 4.8-1.2 8-4.5 8-9V6l-8-3Z" />
                <path d="m9 12 2.2 2.2L15.5 10" />
            </svg>
            Sign in with OLFU account
        </button>

        <div class="divider" role="separator">or use email code</div>

        {{-- Static for now. No submit route exists yet; wire up when auth lands. --}}
        <form class="stack" novalidate>
            <div class="field">
                <label class="field__label" for="student_id">Student ID</label>
                <input
                    class="input"
                    type="text"
                    id="student_id"
                    name="student_id"
                    placeholder="Student ID"
                    autocomplete="username"
                    inputmode="numeric"
                >
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
                >
            </div>

            <button type="button" class="btn btn--outline btn--block">Send code</button>
        </form>

        <p class="auth-footer">
            No account yet? <a href="{{ url('/register') }}">Register</a>
        </p>

        <p class="auth-disclaimer">
            Your name stays hidden unless you choose to show it.
        </p>

    </div>
@endsection
