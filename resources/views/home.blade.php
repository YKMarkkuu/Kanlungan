@extends('layouts.app')

@section('title', 'Home · Kanlungan')

@section('content')
    {{-- TODO(Phase D): replace the inline padding with a real app layout class. --}}
    <div class="container" style="padding-top: 40px; padding-bottom: 32px;">
        <div class="stack--lg stack">

            <header>
                <h1>You're in.</h1>
                <p class="text-muted">Auth is working. The feed is coming soon.</p>
            </header>

            <div class="field__error" id="logout-banner" aria-live="polite" hidden></div>

            {{-- Logout is a fetch (see public/js/home.js) because /auth/logout returns JSON. --}}
            <button type="button" class="btn btn--outline btn--block" id="logout-btn">Log out</button>

        </div>
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('js/home.js') }}" defer></script>
@endsection
