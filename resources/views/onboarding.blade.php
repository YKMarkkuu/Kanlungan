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
                <div class="onboarding__copy onboarding__copy--demo">
                    <p class="onboarding__step">02 <span>Anonymity</span></p>
                    <h2>Anonymity when you need it.</h2>
                    <p>Posts can be anonymous, protected by a per-thread alias. The system keeps you accountable, but other students never see who you are.</p>
                </div>
                <div class="onboarding__demo cm-onboarding-demo onboarding__demo--composer">
                    <div class="onboarding__demo-window">
                        <div class="onboarding__demo-heading">
                            <strong>Create Post</strong>
                            <span>Ask</span>
                        </div>
                        <textarea aria-label="Example post composer" readonly>What's on your mind?</textarea>
                        <div class="cm-demo-target cm-demo-target--anon">
                            <button type="button" class="cm-anon-badge" id="onboarding-anon-toggle" aria-pressed="true">
                                <span class="cm-anon-badge__pill">
                                    @include('components.icon', ['name' => 'mask'])
                                    <span>Anonymous</span>
                                </span>
                            </button>
                            <div class="cm-ghost-cursor" aria-hidden="true">@include('components.icon', ['name' => 'pointer'])</div>
                        </div>
                    </div>
                    <p class="onboarding__demo-caption" id="onboarding-anon-caption" aria-live="polite">
                        <span>You'll appear as Anonymous Falcon</span>
                    </p>
                </div>
            </article>

            <article class="onboarding__slide" data-onboarding-slide aria-hidden="true" inert>
                <div class="onboarding__copy onboarding__copy--demo">
                    <p class="onboarding__step">03 <span>Helpfulness</span></p>
                    <h2>Helpfulness is rewarded.</h2>
                    <p>Earn points by marking comments as Helpful on Ask, Help, and Academic posts. Build your reputation.</p>
                </div>
                <div class="onboarding__demo cm-onboarding-demo onboarding__demo--helpful">
                    <div class="onboarding__demo-comment">
                        <strong>Campus question</strong>
                        <p>Check with your department office for the latest schedule.</p>
                        <div class="cm-demo-target cm-demo-target--helpful">
                            <button type="button" class="onboarding__helpful-btn" id="onboarding-helpful-btn" aria-pressed="false">
                                @include('components.icon', ['name' => 'star'])
                                <span>Helpful</span>
                            </button>
                            <div class="cm-ghost-cursor" aria-hidden="true">@include('components.icon', ['name' => 'pointer'])</div>
                        </div>
                    </div>
                    <p class="onboarding__demo-points" aria-live="polite">
                        <span id="onboarding-points-count">0</span> points
                        <span class="onboarding__points-earned" id="onboarding-points-earned" hidden>+3</span>
                    </p>
                </div>
            </article>

            <article class="onboarding__slide" data-onboarding-slide aria-hidden="true" inert>
                <div class="onboarding__copy onboarding__copy--demo">
                    <p class="onboarding__step">04 <span>Safety</span></p>
                    <h2>Safety first.</h2>
                    <p>If you see something concerning, report it. Safety reports go straight to moderators.</p>
                </div>
                <div class="onboarding__demo cm-onboarding-demo onboarding__demo--safety">
                    <div class="onboarding__demo-post">
                        <div class="onboarding__demo-post-heading">
                            <strong>Campus community</strong>
                            <div class="cm-demo-target cm-demo-target--report">
                                <button type="button" id="onboarding-report-menu-toggle" aria-label="More post actions" aria-expanded="false" aria-controls="onboarding-report-menu">
                                    @include('components.icon', ['name' => 'more'])
                                </button>
                                <div class="cm-ghost-cursor" aria-hidden="true">@include('components.icon', ['name' => 'pointer'])</div>
                            </div>
                        </div>
                        <p>Everyone deserves to feel safe here.</p>
                        <div class="onboarding__report-menu" id="onboarding-report-menu" hidden>
                            <button type="button" id="onboarding-report-option">@include('components.icon', ['name' => 'flag']) Report</button>
                            <p id="onboarding-report-note" hidden>Critical reports go straight to moderators.</p>
                        </div>
                    </div>
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
    <script src="{{ asset('js/community.js') }}" defer></script>
    <script src="{{ asset('js/onboarding.js') }}" defer></script>
@endsection
