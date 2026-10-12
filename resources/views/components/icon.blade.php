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
        @case('check') <path d="m5 12.5 4.5 4.5L19 7.5" /> @break
    @endswitch
</svg>
