<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1a6b4a">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Staff · Kanlungan')</title>

    <link rel="stylesheet" href="{{ asset('css/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components.css') }}">
    <link rel="stylesheet" href="{{ asset('css/staff.css') }}">
</head>
<body class="sf">
    @php
        $me = auth()->user();
        $roleName = ['admin' => 'Admin', 'guidance' => 'Guidance', 'moderator' => 'Moderator'][$me->role] ?? ucfirst((string) $me->role);
    @endphp

    <aside class="sf-rail">
        <a class="sf-brand" href="{{ route('staff.dashboard') }}">
            <span class="sf-mark" aria-hidden="true">@include('components.icon', ['name' => 'shield'])</span>
            <span class="sf-brand__text"><strong>Kanlungan</strong><small>Staff</small></span>
        </a>

        <nav class="sf-nav" aria-label="Staff">
            <a href="{{ route('staff.dashboard') }}" class="{{ request()->routeIs('staff.dashboard') ? 'is-active' : '' }}">
                @include('components.icon', ['name' => 'grid']) Overview
            </a>
            <a href="{{ route('staff.reports') }}" class="{{ request()->routeIs('staff.reports', 'staff.case') ? 'is-active' : '' }}">
                @include('components.icon', ['name' => 'inbox']) Reports
                @if ($nav['review'] > 0)
                    <span class="sf-count {{ $nav['critical'] > 0 ? 'sf-count--alert' : '' }}" aria-label="{{ $nav['review'] }} need a decision">{{ $nav['review'] }}</span>
                @endif
            </a>
            <a href="{{ route('staff.sanctions') }}" class="{{ request()->routeIs('staff.sanctions') ? 'is-active' : '' }}">
                @include('components.icon', ['name' => 'lock']) Restricted accounts
                @if ($nav['sanctions'] > 0)<span class="sf-count">{{ $nav['sanctions'] }}</span>@endif
            </a>
            <a href="{{ route('staff.activity') }}" class="{{ request()->routeIs('staff.activity') ? 'is-active' : '' }}">
                @include('components.icon', ['name' => 'list']) Audit log
            </a>
        </nav>

        <div class="sf-foot">
            <p class="sf-who">{{ $me->name }}<small>{{ $roleName }}</small></p>
            <form method="POST" action="{{ route('staff.logout') }}">
                @csrf
                <button type="submit" class="sf-signout">@include('components.icon', ['name' => 'logout']) Sign out</button>
            </form>
        </div>
    </aside>

    <main class="sf-main">
        @if (session('staff_status'))
            <div class="sf-notice" role="status" aria-live="polite">{{ session('staff_status') }}</div>
        @endif
        @if ($errors->any())
            <div class="sf-notice sf-notice--error" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        @yield('content')
    </main>

    <script src="{{ asset('js/staff.js') }}" defer></script>
</body>
</html>
