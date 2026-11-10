{{-- Inline SVG icons (no icon fonts, no emoji). Usage: @include('components.icon', ['name' => 'flag']) --}}
<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('home') <path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1V10Z" /> @break
        @case('search') <circle cx="11" cy="11" r="6.5" /><path d="m16 16 4 4" /> @break
        @case('help') <circle cx="12" cy="12" r="9" /><path d="M9.6 9a2.5 2.5 0 1 1 4.3 1.8c-1 .9-1.9 1.3-1.9 2.7" /><path d="M12 17h.01" /> @break
        @case('bell') <path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15L6 16Z" /><path d="M10 20.5h4" /> @break
        @case('user') <circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" /> @break
        @case('plus') <path d="M12 5v14M5 12h14" /> @break
        @case('minus') <path d="M5 12h14" /> @break
        @case('back') <path d="m15 5-7 7 7 7" /> @break
        @case('close') <path d="M6 6l12 12M18 6 6 18" /> @break
        @case('flag') <path d="M6 20V5M6 5h11l-2 4 2 4H6" /> @break
        @case('like') <path d="M8 11v9H4v-9h4Z" /><path d="M8 11l4-7a2 2 0 0 1 2 2v3h5a1.5 1.5 0 0 1 1.5 1.7l-1 6.5A2 2 0 0 1 17.5 19H8" /> @break
        @case('comment') <path d="M4 5h16v11H9l-5 4V5Z" /> @break
        @case('share') <path d="M14 4h6v6M20 4l-9 9" /><path d="M18 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5" /> @break
        @case('star') <path d="m12 4 2.4 5 5.4.7-4 3.7 1 5.4L12 16.2 7.2 18.8l1-5.4-4-3.7 5.4-.7L12 4Z" /> @break
        @case('more') <circle cx="5" cy="12" r="1" /><circle cx="12" cy="12" r="1" /><circle cx="19" cy="12" r="1" /> @break
        @case('mask') <path d="M3 9c3-1 6-1 9 0s6 1 9 0v3a4 4 0 0 1-4 4h-2.5L12 14l-1.5 2H8a4 4 0 0 1-4-4V9Z" /> @break
        @case('clip') <path d="m20 11-8.5 8.5a5 5 0 0 1-7-7L13 4a3.5 3.5 0 0 1 5 5l-8.5 8.5a2 2 0 0 1-3-3L14 7" /> @break
        @case('shield') <path d="M12 3 5 6v5c0 4.5 3 8 7 10 4-2 7-5.5 7-10V6l-7-3Z" /> @break
        @case('scale') <path d="M12 3v18M5 6h14M7 6l-4 7h8L7 6Zm10 0-4 7h8l-4-7ZM8 21h8" /> @break
        @case('check') <path d="m5 12.5 4.5 4.5L19 7.5" /> @break
        @case('send') <path d="M22 2 11 13" /><path d="M22 2 15 22l-4-9-9-4 20-7Z" /> @break
        @case('bookmark') <path d="M6 4h12v17l-6-4.5L6 21V4Z" /> @break
        @case('smile') <circle cx="12" cy="12" r="9" /><path d="M8.5 14.5c.9 1.2 2.1 1.8 3.5 1.8s2.6-.6 3.5-1.8" /><path d="M9 9.5h.01M15 9.5h.01" /> @break
        @case('tag') <path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8Z" /><circle cx="7.5" cy="7.5" r="1" /> @break
        @case('camera') <path d="M4 8h3l1.5-2h7L17 8h3a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1Z" /><circle cx="12" cy="13" r="3.5" /> @break
        @case('pointer') <path d="M9 11V5a2 2 0 0 1 4 0v5-2a2 2 0 0 1 4 0v2-1a2 2 0 0 1 4 0v6a6 6 0 0 1-6 6h-2a6 6 0 0 1-4.5-2L4 14a2 2 0 0 1 3-2.7L9 13" /> @break
    @endswitch
</svg>
