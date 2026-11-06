@extends('layouts.staff')

@section('title', 'Reports · Kanlungan staff')

@php
    $views = \App\Services\StaffDashboardService::VIEWS;
    $keep = array_filter(['severity' => $severity, 'type' => $type]);
@endphp

@section('content')
    <header class="sf-head">
        <h1 class="sf-title">Reports</h1>
        <p class="sf-lede">Posts and comments students reported. Open one to read it, see the account's record and decide.</p>
    </header>

    <nav class="sf-tabs" aria-label="Report queues">
        @foreach ($views as $key => $label)
            <a href="{{ route('staff.reports', ['view' => $key] + $keep) }}" class="{{ $view === $key ? 'is-active' : '' }}">
                {{ $label }} <span class="sf-count">{{ $tabCounts[$key] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="sf-filters" aria-label="Filters">
        @foreach ([null => 'Any severity', 'critical' => 'Critical', 'high' => 'High', 'medium' => 'Medium'] as $key => $label)
            <a class="chip {{ $severity === $key ? 'chip--active' : '' }}" href="{{ route('staff.reports', array_filter(['view' => $view, 'severity' => $key, 'type' => $type])) }}">{{ $label }}</a>
        @endforeach
        <span aria-hidden="true" style="width: 8px;"></span>
        @foreach ([null => 'Posts and comments', 'post' => 'Posts', 'comment' => 'Comments'] as $key => $label)
            <a class="chip {{ $type === $key ? 'chip--active' : '' }}" href="{{ route('staff.reports', array_filter(['view' => $view, 'severity' => $severity, 'type' => $key])) }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="sf-list">
        @forelse ($reports as $r)
            @include('staff._row', ['r' => $r])
        @empty
            <p class="sf-empty">No reports here{{ $keep ? ' with these filters' : '' }}.</p>
        @endforelse
    </div>

    @if ($reports->previousPageUrl() || $reports->nextPageUrl())
        <nav class="sf-pager" aria-label="Pages">
            @if ($reports->previousPageUrl())<a class="btn btn--outline" href="{{ $reports->previousPageUrl() }}">Newer</a>@else<span></span>@endif
            @if ($reports->nextPageUrl())<a class="btn btn--outline" href="{{ $reports->nextPageUrl() }}">Older</a>@endif
        </nav>
    @endif
@endsection
