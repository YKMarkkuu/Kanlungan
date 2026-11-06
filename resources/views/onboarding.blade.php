@extends('layouts.guest')

@section('title', 'Welcome · Kanlungan')
@section('guest-class', 'guest-page--onboarding')
@section('body-class', 'onboarding-page')

@section('head')
    <link rel="stylesheet" href="{{ asset('css/onboarding.css') }}">
@endsection

@section('content')
    <section class="onboarding" aria-label="How Kanlungan works" data-onboarding>
        <header class="onboarding__header">
            <button type="submit" class="onboarding__skip" form="onboarding-completion">Skip</button>
        </header>

        <form id="onboarding-completion" method="POST" action="{{ route('onboarding.complete') }}" hidden>
            @csrf
        </form>

        <div class="onboarding__slides" data-onboarding-slides>
            <article class="onboarding__slide is-active" data-onboarding-slide aria-hidden="false">
                <div class="onboarding__illustration onboarding__illustration--welcome" aria-hidden="true">
                    @include('components.icon', ['name' => 'home'])
                </div>
                <div class="onboarding__copy">
                    <p class="onboarding__step">01 <span>Welcome</span></p>
                    <h1>Welcome to Kanlungan.</h1>
                    <p>A safe, verified space for OLFU Pampanga.</p>
                </div>
            </article>

            <article class="onboarding__slide" data-onboarding-slide aria-hidden="true" inert>
                <div class="onboarding__illustration onboarding__illustration--anonymity" aria-hidden="true">
                    @include('components.icon', ['name' => 'mask'])
                </div>
                <div class="onboarding__copy">
                    <p class="onboarding__step">02 <span>Anonymity</span></p>
                    <h2>Anonymity when you need it.</h2>
                    <p>Posts can be anonymous, protected by a per-thread alias. The system keeps you accountable, but other students never see who you are.</p>
                </div>
            </article>

            <article class="onboarding__slide" data-onboarding-slide aria-hidden="true" inert>
                <div class="onboarding__illustration onboarding__illustration--helpful" aria-hidden="true">
                    @include('components.icon', ['name' => 'star'])
                </div>
                <div class="onboarding__copy">
                    <p class="onboarding__step">03 <span>Helpfulness</span></p>
                    <h2>Helpfulness is rewarded.</h2>
                    <p>Earn points by marking comments as Helpful on Ask, Help, and Academic posts. Build your reputation.</p>
                </div>
            </article>

            <article class="onboarding__slide" data-onboarding-slide aria-hidden="true" inert>
                <div class="onboarding__illustration onboarding__illustration--safety" aria-hidden="true">
                    @include('components.icon', ['name' => 'shield'])
                </div>
                <div class="onboarding__copy">
                    <p class="onboarding__step">04 <span>Safety</span></p>
                    <h2>Safety first.</h2>
                    <p>If you see something concerning, report it. Safety reports go straight to moderators.</p>
                </div>
            </article>
        </div>

        <footer class="onboarding__footer">
            <div class="onboarding__progress" aria-label="Onboarding progress">
                <span class="onboarding__progress-dot is-active" data-onboarding-dot aria-hidden="true"></span>
                <span class="onboarding__progress-dot" data-onboarding-dot aria-hidden="true"></span>
                <span class="onboarding__progress-dot" data-onboarding-dot aria-hidden="true"></span>
                <span class="onboarding__progress-dot" data-onboarding-dot aria-hidden="true"></span>
            </div>
            <span class="visually-hidden" data-onboarding-status aria-live="polite">Step 1 of 4</span>
            <button type="button" class="btn btn--primary onboarding__button" data-onboarding-next>Next</button>
            <button type="submit" class="btn btn--primary onboarding__button" data-onboarding-complete form="onboarding-completion" hidden>Let's go!</button>
        </footer>
    </section>
@endsection

@section('scripts')
    <script src="{{ asset('js/onboarding.js') }}" defer></script>
@endsection
