@extends('layouts.staff')

@section('title', 'Audit log · Kanlungan staff')

@php use App\Services\StaffDashboardService as Staff; @endphp

@section('content')
    <header class="sf-head">
        <h1 class="sf-title">Audit log</h1>
        <p class="sf-lede">Every decision and every identity reveal, with who did it and why. Nothing here can be edited.</p>
    </header>

    <div class="sf-table">
        @forelse ($log as $a)
            <div class="sf-table__row">
                <span class="text-muted">{{ Staff::when($a['at']) }}</span>
                <span>
                    <strong>{{ $a['by'] }}</strong>
                    @if ($a['role'])<br><span class="text-small text-muted">{{ ucfirst($a['role']) }}</span>@endif
                </span>
                <span>
                    <b class="{{ $a['action'] === 'unmask' ? 'sf-sev sf-sev--high' : '' }}">{{ $a['label'] }}</b>
                    @if ($a['account']) on {{ $a['account'] }}@endif
                    @if ($a['category']) <span class="text-muted">({{ $a['category'] }})</span>@endif
                    <br><span class="text-muted">{{ $a['reason'] }}</span>
                </span>
                @if ($a['report_id'])<a href="{{ route('staff.case', $a['report_id']) }}">Case {{ $a['report_id'] }}</a>@else<span></span>@endif
            </div>
        @empty
            <p class="sf-empty">Nothing has been logged yet.</p>
        @endforelse
    </div>

    @if ($log->previousPageUrl() || $log->nextPageUrl())
        <nav class="sf-pager" aria-label="Pages">
            @if ($log->previousPageUrl())<a class="btn btn--outline" href="{{ $log->previousPageUrl() }}">Newer</a>@else<span></span>@endif
            @if ($log->nextPageUrl())<a class="btn btn--outline" href="{{ $log->nextPageUrl() }}">Older</a>@endif
        </nav>
    @endif
@endsection
