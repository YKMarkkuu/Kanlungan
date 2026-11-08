@props(['alt' => ''])
{{-- Brand mark. Uses a custom asset when present, otherwise the built-in house. --}}
@php
    $custom = null;

    foreach (['svg', 'png'] as $extension) {
        $file = public_path("images/logo.{$extension}");

        if (is_file($file)) {
            $custom = asset("images/logo.{$extension}").'?v='.filemtime($file);
            break;
        }
    }
@endphp
@if ($custom)
    <img {{ $attributes->class(['kl-logo']) }} src="{{ $custom }}" alt="{{ $alt }}" decoding="async">
@else
    <svg {{ $attributes->class(['kl-logo']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
        <path d="M3 11.5 12 4l9 7.5" />
        <path d="M5.5 10v9.5h13V10" />
        <path d="M10 19.5v-5h4v5" />
    </svg>
@endif
