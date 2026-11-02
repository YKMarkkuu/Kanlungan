@extends('layouts.community')

@section('title', 'Community · Kanlungan')
@section('nav', 'home')

@section('content')
    <div class="stack">
        {{-- Visible on desktop, visually hidden on mobile (see .cm-feed-title). Keeps the page heading for screen readers. --}}
        <header class="cm-header cm-feed-title">
            <h1>Community</h1>
        </header>

        @if (session('status'))
            <div class="card cm-notice" role="status" aria-live="polite">{{ session('status') }}</div>
        @endif

        <nav class="cm-chips cm-chips--sticky" aria-label="Filter posts">
            <a href="{{ route('feed') }}" class="chip {{ $active === null ? 'chip--active' : '' }}">All</a>
            @foreach ($categories as $category)
                <a href="{{ route('feed', ['category' => $category]) }}" class="chip {{ $active === $category ? 'chip--active' : '' }}">{{ $category }}</a>
            @endforeach
        </nav>

        @if ($hasPendingReports)
            <div class="card cm-notice">There are active community reports waiting for a verdict. <a href="{{ route('alerts', ['tab' => 'verdicts']) }}">Go to Reports</a></div>
        @endif

        <div id="skeleton-loader" class="hidden" aria-hidden="true">
            <div class="cm-skeleton cm-skeleton--post"></div>
            <div class="cm-skeleton cm-skeleton--post"></div>
            <div class="cm-skeleton cm-skeleton--post"></div>
        </div>

        <div id="feed-posts" class="cm-feed-list">
            @forelse ($posts as $post)
                @include('components.post-card', ['post' => $post, 'showReportStatus' => false])
            @empty
                <x-empty-state icon="home" title="It's quiet here" message="Be the first to post on Kanlungan." />
            @endforelse
        </div>

        @if ($posts->hasMorePages())
            <div id="load-more-container" class="cm-load-more">
                <button type="button" id="load-more-btn" class="btn btn--outline"
                        data-next-url="{{ $posts->nextPageUrl() }}">Load more</button>
                <p id="load-more-error" class="field__error" role="alert" hidden></p>
            </div>
        @endif
    </div>

@endsection
