<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#1a6b4a">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Kanlungan')</title>

    <link rel="stylesheet" href="{{ asset('css/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components.css') }}">
    @yield('head')
</head>
<body>
    <div class="page">
        <main>
            @yield('content')
        </main>

        {{-- TODO(Phase D): add bottom nav --}}
    </div>

    @yield('scripts')
</body>
</html>
