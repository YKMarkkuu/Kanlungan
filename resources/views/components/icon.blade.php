{{-- Inline SVG icons (no icon fonts, no emoji). Usage: @include('components.icon', ['name' => 'flag']) --}}
<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('search') <circle cx="11" cy="11" r="6.5" /><path d="m16 16 4 4" /> @break
        @case('bell') <path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15L6 16Z" /><path d="M10 20.5h4" /> @break
        @case('plus') <path d="M12 5v14M5 12h14" /> @break
        @case('back') <path d="m15 5-7 7 7 7" /> @break
        @case('close') <path d="M6 6l12 12M18 6 6 18" /> @break
        @case('flag') <path d="M6 20V5M6 5h11l-2 4 2 4H6" /> @break
        @case('like') <path d="M8 11v9H4v-9h4Z" /><path d="M8 11l4-7a2 2 0 0 1 2 2v3h5a1.5 1.5 0 0 1 1.5 1.7l-1 6.5A2 2 0 0 1 17.5 19H8" /> @break
        @case('comment') <path d="M4 5h16v11H9l-5 4V5Z" /> @break
        @case('star') <path d="m12 4 2.4 5 5.4.7-4 3.7 1 5.4L12 16.2 7.2 18.8l1-5.4-4-3.7 5.4-.7L12 4Z" /> @break
        @case('more') <circle cx="5" cy="12" r="1" /><circle cx="12" cy="12" r="1" /><circle cx="19" cy="12" r="1" /> @break
        @case('mask') <path d="M3 9c3-1 6-1 9 0s6 1 9 0v3a4 4 0 0 1-4 4h-2.5L12 14l-1.5 2H8a4 4 0 0 1-4-4V9Z" /> @break
        @case('clip') <path d="m20 11-8.5 8.5a5 5 0 0 1-7-7L13 4a3.5 3.5 0 0 1 5 5l-8.5 8.5a2 2 0 0 1-3-3L14 7" /> @break
        @case('shield') <path d="M12 3 5 6v5c0 4.5 3 8 7 10 4-2 7-5.5 7-10V6l-7-3Z" /> @break
        @case('inbox') <path d="M4 13.5 6.5 5h11L20 13.5V19H4v-5.5Z" /><path d="M4 13.5h4.5a3.5 3.5 0 0 0 7 0H20" /> @break
        @case('heart') <path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10Z" /> @break
        @case('vote') <path d="M5 12.5 9.5 17 19 7" /><path d="M4 20h16" /> @break
        @case('grid') <path d="M4 4h6.5v6.5H4V4ZM13.5 4H20v6.5h-6.5V4ZM4 13.5h6.5V20H4v-6.5ZM13.5 13.5H20V20h-6.5v-6.5Z" /> @break
        @case('lock') <rect x="5" y="11" width="14" height="9" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /> @break
        @case('list') <path d="M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01" /> @break
        @case('logout') <path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4" /><path d="M10 8l-4 4 4 4M6 12h10" /> @break
        @case('eye') <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" /><circle cx="12" cy="12" r="3" /> @break
        @case('check') <path d="m5 12.5 4.5 4.5L19 7.5" /> @break
    @endswitch
</svg>
