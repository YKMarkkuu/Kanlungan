@extends('layouts.guest')

@section('title', 'Welcome · Kanlungan')
@section('guest-class', 'guest-page--onboarding')

@section('head')
    <link rel="stylesheet" href="{{ asset('css/onboarding.css') }}">
@endsection

@section('content')
    <section class="onboarding" aria-labelledby="onboarding-title" data-onboarding>
        <header class="onboarding__header">
            <div class="onboarding__mark" aria-hidden="true">K</div>
            <p class="onboarding__eyebrow">Your campus community</p>
            <h1 id="onboarding-title">Welcome to Kanlungan</h1>
        </header>

        <div class="onboarding__progress" aria-label="Onboarding progress">
            <span class="onboarding__progress-dot is-active" data-onboarding-dot aria-hidden="true"></span>
            <span class="onboarding__progress-dot" data-onboarding-dot aria-hidden="true"></span>
            <span class="onboarding__progress-dot" data-onboarding-dot aria-hidden="true"></span>
            <span class="visually-hidden" data-onboarding-status aria-live="polite">Step 1 of 3</span>
        </div>

        <div class="onboarding__slides" data-onboarding-slides>
            <article class="onboarding__slide is-active" data-onboarding-slide aria-hidden="false">
                <div class="onboarding__illustration onboarding__illustration--welcome" aria-hidden="true">
                    <span class="onboarding__illustration-home">⌂</span>
                    <span class="onboarding__illustration-spark onboarding__illustration-spark--one"></span>
                    <span class="onboarding__illustration-spark onboarding__illustration-spark--two"></span>
                </div>
                <div class="onboarding__copy">
                    <p class="onboarding__step">01 <span>Welcome</span></p>
                    <h2>A safe, verified space for OLFU Pampanga students.</h2>
                </div>
                <button type="button" class="btn btn--primary onboarding__button" data-onboarding-next>Continue</button>
            </article>

            <article class="onboarding__slide" data-onboarding-slide aria-hidden="false">
                <div class="onboarding__illustration onboarding__illustration--privacy" aria-hidden="true">
                    <span class="onboarding__shield">✓</span>
                    <span class="onboarding__alias">Anonymous · 4821</span>
                </div>
                <div class="onboarding__copy">
                    <p class="onboarding__step">02 <span>Anonymity</span></p>
                    <h2>Post anonymously. Your identity is protected by a per-thread alias, but the system keeps you accountable.</h2>
                </div>
                <button type="button" class="btn btn--primary onboarding__button" data-onboarding-next>Continue</button>
            </article>

            <article class="onboarding__slide" data-onboarding-slide aria-hidden="false">
                <div class="onboarding__illustration onboarding__illustration--points" aria-hidden="true">
                    <span class="onboarding__points-star">★</span>
                    <span class="onboarding__points-label">+3 points</span>
                </div>
                <div class="onboarding__copy">
                    <p class="onboarding__step">03 <span>Points</span></p>
                    <h2>Earn points by being helpful on Ask, Help, and Academic posts.</h2>
                </div>
                <form method="POST" action="{{ route('onboarding.complete') }}">
                    @csrf
                    <button type="submit" class="btn btn--primary onboarding__button">Let's go!</button>
                </form>
            </article>
        </div>
    </section>
@endsection

@section('scripts')
    <script src="{{ asset('js/onboarding.js') }}" defer></script>
@endsection
