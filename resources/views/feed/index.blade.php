@extends('layouts.community')

@section('title', 'Community · Kanlungan')
@section('nav', 'home')

@section('content')
    <div class="stack">
        <header class="cm-header">
            <h1>Community</h1>
            <div class="cm-header__actions">
                <a href="{{ route('search') }}" class="cm-icon-btn" aria-label="Search">@include('components.icon', ['name' => 'search'])</a>
                @php($unread = \App\Models\Alert::unreadCount(auth()->id()))
                <a href="{{ route('alerts') }}" class="cm-icon-btn cm-bell" aria-label="Alerts">@include('components.icon', ['name' => 'bell'])@if ($unread > 0)<span class="cm-count">{{ $unread }}</span>@endif</a>
            </div>
        </header>

        @if (session('status'))
            <div class="card cm-notice" role="status" aria-live="polite">{{ session('status') }}</div>
        @endif

        <nav class="cm-chips" aria-label="Filter posts">
            <a href="{{ route('feed') }}" class="chip {{ $active === null ? 'chip--active' : '' }}">All</a>
            @foreach ($categories as $category)
                <a href="{{ route('feed', ['category' => $category]) }}" class="chip {{ $active === $category ? 'chip--active' : '' }}">{{ $category }}</a>
            @endforeach
        </nav>

        @if ($hasPendingReports)
            <div class="card cm-notice">There are active community reports waiting for a verdict. <a href="{{ route('reports.index') }}">Go to Reports</a></div>
        @endif

        <p class="text-small text-muted">Sort: Newest</p>

        @forelse ($posts as $post)
            @include('components.post-card', ['post' => $post, 'showReportStatus' => false])
        @empty
            <div class="card cm-empty">
                <p><strong>No posts here yet.</strong></p>
                <p class="text-small text-muted">Be the first to post in this category.</p>
            </div>
        @endforelse
    </div>

@endsection
