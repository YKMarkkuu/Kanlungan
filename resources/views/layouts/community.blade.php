<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1a6b4a">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Kanlungan')</title>

    <link rel="stylesheet" href="{{ asset('css/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components.css') }}">
    <link rel="stylesheet" href="{{ asset('css/community.css') }}">
</head>
<body>
    <div class="page cm-page">
        <main class="container cm-main @yield('main-class')">
            @if ($errors->has('report'))
                <div class="card cm-notice cm-notice--error" role="alert">{{ $errors->first('report') }}</div>
            @endif
            @if (session('report_status'))
                <div class="card cm-notice" role="status" aria-live="polite">{{ session('report_status') }}</div>
            @endif
            @yield('content')
        </main>

        {{-- Bottom nav (design screen 3). Pages that need the whole screen (create post, comment box)
             set @section('hide-nav'). @section('nav', 'home|search|alerts|me') highlights a tab. --}}
        @if (trim($__env->yieldContent('hide-nav')) === '')
            @php($unread = auth()->check() ? \App\Models\Alert::unreadCount(auth()->id()) : 0)
            @php($tab = trim($__env->yieldContent('nav')))
            <nav class="cm-nav" aria-label="Main">
                <a href="{{ route('feed') }}" class="cm-nav__item {{ $tab === 'home' ? 'is-active' : '' }}">Home</a>
                <a href="{{ route('search') }}" class="cm-nav__item {{ $tab === 'search' ? 'is-active' : '' }}">Search</a>
                <a href="{{ route('alerts') }}" class="cm-nav__item {{ $tab === 'alerts' ? 'is-active' : '' }}">Alerts
                    @if ($unread > 0)<span class="cm-count" aria-label="{{ $unread }} unread">{{ $unread }}</span>@endif
                </a>
                <a href="{{ route('community.me') }}" class="cm-nav__item {{ $tab === 'me' ? 'is-active' : '' }}">Me</a>
            </nav>
        @endif
    </div>

    @include('components.report-sheet')

    <script src="{{ asset('js/community.js') }}" defer></script>
</body>
</html>
