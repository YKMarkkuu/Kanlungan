@extends('layouts.guest')

@section('title', 'Welcome · Kanlungan')
@section('guest-class', 'guest-page--onboarding')
@section('body-class', 'onboarding-page')

@php
    // One entry per demo slide. "key" selects the sandbox in onboarding/_stage.blade.php and the
    // scene factory in community.js (data-scene). Adding a slide = one entry here + one scene there.
    $scenes = [
        ['key' => 'feed', 'label' => 'Feed', 'title' => 'Your campus feed.',
            'copy' => 'Like a post, or open its thread to join the conversation.'],
        ['key' => 'thread', 'label' => 'Helpfulness', 'title' => 'Helpfulness is rewarded.',
            'copy' => 'Earn points by marking comments as Helpful on Ask, Help, and Academic posts. Build your reputation.'],
        ['key' => 'compose', 'label' => 'Anonymity', 'title' => 'Anonymity when you need it.',
            'copy' => 'Posts can be anonymous, protected by a per-thread alias. The system keeps you accountable, but other students never see who you are.'],
        ['key' => 'market', 'label' => 'Marketplace', 'title' => 'Buy and sell on campus.',
            'copy' => 'Listings show a price badge and an itemized list. Sellers always appear under their display name.'],
        ['key' => 'report', 'label' => 'Safety', 'title' => 'Safety first.',
            'copy' => 'If you see something concerning, report it. Safety reports go straight to moderators.'],
    ];
    $totalSlides = count($scenes) + 1;
@endphp

@section('head')
    <link rel="stylesheet" href="{{ asset('css/onboarding.css') }}">
    {{-- Ghost hand, ripple and toast transitions live in community.css (after onboarding.css so they win). --}}
    <link rel="stylesheet" href="{{ asset('css/community.css') }}">
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
                    <x-logo />
                </div>
                <div class="onboarding__copy">
                    <p class="onboarding__step">01 <span>Welcome</span></p>
                    <h1>Welcome to Kanlungan.</h1>
                    <p>A safe, verified space for OLFU Pampanga.</p>
                </div>
            </article>

            @foreach ($scenes as $index => $scene)
                <article class="onboarding__slide" data-onboarding-slide data-scene="{{ $scene['key'] }}" aria-hidden="true" inert>
                    <div class="onboarding__copy onboarding__copy--demo">
                        <p class="onboarding__step">{{ str_pad($index + 2, 2, '0', STR_PAD_LEFT) }} <span>{{ $scene['label'] }}</span></p>
                        <h2>{{ $scene['title'] }}</h2>
                        <p>{{ $scene['copy'] }}</p>
                    </div>

                    {{-- [data-demo-stage] is the coordinate space for the ghost hand: the real UI lives in the
                         sandbox, the hand and its ripples are siblings of it (never inside a transformed node). --}}
                    <div class="onboarding__demo cm-onboarding-demo ob-stage" data-demo-stage aria-hidden="true" inert>
                        @include('onboarding._stage', ['scene' => $scene['key']])
                        <div class="cm-ghost-cursor" data-demo-cursor aria-hidden="true"><span class="cm-ghost-cursor__hand">@include('components.icon', ['name' => 'pointer'])</span></div>
                    </div>
                </article>
            @endforeach
        </div>

        <footer class="onboarding__footer">
            <div class="onboarding__progress" aria-label="Onboarding progress">
                @for ($i = 0; $i < $totalSlides; $i++)
                    <span class="onboarding__progress-dot {{ $i === 0 ? 'is-active' : '' }}" data-onboarding-dot aria-hidden="true"></span>
                @endfor
            </div>
            <span class="visually-hidden" data-onboarding-status aria-live="polite">Step 1 of {{ $totalSlides }}</span>
            <button type="button" class="btn btn--primary onboarding__button" data-onboarding-next>Next</button>
            <button type="submit" class="btn btn--primary onboarding__button" data-onboarding-complete form="onboarding-completion" hidden>Let's go!</button>
        </footer>
    </section>
@endsection

@section('scripts')
    <script src="{{ asset('js/community.js') }}" defer></script>
    <script src="{{ asset('js/onboarding.js') }}" defer></script>
@endsection
