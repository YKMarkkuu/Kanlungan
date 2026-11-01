<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1a6b4a">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- PWA --}}
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/icon-192.png') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Kanlungan">

    <title>@yield('title', 'Kanlungan')</title>

    <link rel="stylesheet" href="{{ asset('css/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components.css') }}">
    @yield('head')
</head>
<body>
    <div class="page">
        <main class="guest-page">
            <div class="container">
                @yield('content')
            </div>
        </main>
    </div>

    @yield('scripts')
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.js').catch(function (error) {
                    console.error('Service worker registration failed:', error);
                });
            });
        }
    </script>
</body>
</html>
