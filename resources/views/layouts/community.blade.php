<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    {{-- viewport-fit=cover is required for env(safe-area-inset-bottom) to return a non-zero value on iPhones. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#1a6b4a">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- HTMX settings. htmx reads this tag once at startup, so it must stay above the htmx <script>.
         historyCacheSize 0 + refreshOnHistoryMiss: htmx would otherwise save page snapshots (anonymous posts,
           alerts, saved posts) in localStorage. Back/Forward becomes a normal browser load instead.
         allowScriptTags false: htmx never executes <script> tags found in swapped HTML.
         disableSelector: htmx ignores forms (they have their own JS: confirm modal, AJAX votes, uploads, CSRF
           redirects), links with a #fragment (the fragment is lost in XHR redirects) and /alerts/{id} links
           (they redirect to a post#comment). Those stay normal browser navigations.
         includeIndicatorStyles false: we draw our own progress bar. --}}
    <meta name="htmx-config" content='{"historyCacheSize":0,"refreshOnHistoryMiss":true,"allowScriptTags":false,"allowEval":false,"includeIndicatorStyles":false,"scrollIntoViewOnBoost":false,"defaultSettleDelay":0,"defaultSwapDelay":0,"timeout":15000,"disableSelector":"[hx-disable], [data-hx-disable], form, a[href*=\"#\"], a[href*=\"/alerts/\"]"}'>

    {{-- PWA --}}
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/icon-192.png') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Kanlungan">

    <title>@yield('title', 'Kanlungan')</title>

    <link rel="stylesheet" href="{{ asset('css/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components.css') }}">
    <link rel="stylesheet" href="{{ asset('css/community.css') }}">
    @yield('head')

    {{-- Pinned version. If this fails to load, every link is still a normal link. --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/htmx/2.0.4/htmx.min.js" defer></script>
</head>
<body>
    <script>
        try {
            if (localStorage.getItem('kanlungan_theme') === 'dark') {
                document.body.classList.add('dark-mode');
            }
        } catch (error) {
            console.error('Could not restore the saved theme preference.', error);
        }
    </script>

    {{-- Global fetch feedback. Outside .cm-page so a page swap never touches it. Driven by community.js. --}}
    <div class="cm-progress" id="cm-progress" aria-hidden="true"><div class="cm-progress__bar"></div></div>

    {{-- Mobile back button is opt-in per page:
         @section('back', 'history')            -> history.back(), falls back to the feed if there is no history
         @section('back', route('post.show', 1)) -> goes to that specific route
         Hidden on desktop by CSS. --}}
    @php($back = trim($__env->yieldContent('back')))
    @php($hideNav = trim($__env->yieldContent('hide-nav')) !== '')

    {{-- hx-boost turns every <a> inside into an AJAX fetch: the response is a full page, htmx keeps only its
         <main> (hx-select) and swaps it in. The bottom nav lives outside <main>, so it is swapped out-of-band
         by id (hx-select-oob). --}}
    <div class="page cm-page"
         hx-boost="true"
         hx-target="main"
         hx-select="main"
         hx-select-oob="#cm-nav"
         hx-swap="outerHTML show:window:top"
         hx-push-url="true">
        <main class="container cm-main @yield('main-class')" @if ($hideNav) data-hide-nav @endif>
            @php($unread = auth()->check() ? \App\Models\Alert::unreadCount(auth()->id()) : 0)
            @php($headerAlerts = auth()->check() ? \App\Models\Alert::where('user_id', auth()->id())->latest('id')->limit(5)->get() : collect())
            <header class="cm-header">
                <div class="cm-header__brand">
                    @if ($back !== '')
                        <a href="{{ $back === 'history' ? route('feed') : $back }}"
                           class="cm-mobile-back"
                           aria-label="Go back">
                            @include('components.icon', ['name' => 'back'])
                        </a>
                    @endif
                    <a href="{{ route('feed') }}" class="cm-brand">Kanlungan</a>
                </div>
                <div class="cm-header__actions">
                    <button type="button" class="cm-icon-btn" aria-label="Help" title="Help" data-restart-onboarding>
                        @include('components.icon', ['name' => 'help'])
                    </button>
                    <a href="{{ route('search') }}" class="cm-icon-btn" aria-label="Search">
                        @include('components.icon', ['name' => 'search'])
                    </a>
                    @include('components.notification-dropdown', ['alerts' => $headerAlerts, 'unread' => $unread])
                </div>
            </header>

            @php($keepFlashCards = trim($__env->yieldContent('keep-flash-cards')) !== '')

            @if ($keepFlashCards)
                @if ($errors->has('report'))
                    <div class="card cm-notice cm-notice--error" role="alert">{{ $errors->first('report') }}</div>
                @endif
                @if (session('report_status'))
                    <div class="card cm-notice" role="status" aria-live="polite">{{ session('report_status') }}</div>
                @endif
            @else
                @foreach (array_filter([session('status'), session('report_status')]) as $flash)
                    <span hidden data-flash-toast data-type="success" data-message="{{ $flash }}"></span>
                @endforeach
                @if ($errors->has('report'))
                    <span hidden data-flash-toast data-type="error" data-message="{{ $errors->first('report') }}"></span>
                @endif
            @endif
            @yield('content')
        </main>

        {{-- Bottom nav (design screen 3). Always rendered, so htmx can swap it by id on every navigation;
             pages that need the whole screen (create post, comment box) set @section('hide-nav') and get
             the `hidden` attribute instead of no nav at all.
             @section('nav', 'home|search|alerts|me') highlights a tab. --}}
        @php($tab = trim($__env->yieldContent('nav')))
        <nav class="cm-nav" id="cm-nav" aria-label="Main navigation" @if ($hideNav) hidden @endif>
            <a href="{{ route('feed') }}" class="cm-nav__item {{ $tab === 'home' ? 'is-active' : '' }}" aria-label="Home" @if ($tab === 'home') aria-current="page" @endif>@include('components.icon', ['name' => 'home'])</a>
            <a href="{{ route('search') }}" class="cm-nav__item {{ $tab === 'search' ? 'is-active' : '' }}" aria-label="Search" @if ($tab === 'search') aria-current="page" @endif>@include('components.icon', ['name' => 'search'])</a>
            <a href="{{ route('post.create') }}" class="cm-nav__item cm-nav__item--create" aria-label="Create post">@include('components.icon', ['name' => 'plus'])</a>
            <a href="{{ route('alerts', ['tab' => 'verdicts']) }}" class="cm-nav__item {{ $tab === 'alerts' ? 'is-active' : '' }}" data-alert-activity-link aria-label="Activity{{ $unread > 0 ? ', '.$unread.' unread' : '' }}" @if ($tab === 'alerts') aria-current="page" @endif>
                @include('components.icon', ['name' => 'bell'])
                <span class="cm-count" data-unread-badge aria-hidden="true" @if ($unread === 0) hidden @endif>{{ $unread }}</span>
            </a>
            <a href="{{ route('community.me') }}" class="cm-nav__item {{ $tab === 'me' ? 'is-active' : '' }}" aria-label="Profile" @if ($tab === 'me') aria-current="page" @endif><x-avatar :avatar-path="auth()->user()->avatar_path" :name="auth()->user()->display_name" class="cm-nav__avatar" /></a>
        </nav>
    </div>

    {{-- Everything below lives outside .cm-page, so it survives page swaps and is bound once. --}}
    @include('components.report-sheet')
    @include('components.confirm-modal')
    @yield('scripts')

    <div class="cm-lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Photo viewer" hidden>
        <button type="button" class="cm-lightbox__close" id="lightbox-close" aria-label="Close photo">@include('components.icon', ['name' => 'close'])</button>
        <span class="cm-lightbox__count" id="lightbox-count" aria-live="polite" hidden></span>
        <button type="button" class="cm-lightbox__nav cm-lightbox__nav--prev" id="lightbox-prev" data-lightbox-nav aria-label="Previous photo" hidden>@include('components.icon', ['name' => 'back'])</button>
        <img class="cm-lightbox__img" id="lightbox-img" src="" alt="">
        <button type="button" class="cm-lightbox__nav cm-lightbox__nav--next" id="lightbox-next" data-lightbox-nav aria-label="Next photo" hidden>@include('components.icon', ['name' => 'back'])</button>
    </div>

    <script src="{{ asset('js/community.js') }}" defer></script>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.js').catch(function (error) {
                    console.error('Service worker registration failed:', error);
                });
            });
        }
    </script>
    <div class="cm-page-overlay" id="cm-page-overlay" aria-hidden="true"></div>
</body>
</html>
