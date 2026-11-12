@extends('layouts.staff')

@section('title', 'Restricted accounts · Kanlungan staff')

@php use App\Services\StaffDashboardService as Staff; @endphp

@section('content')
    <header class="sf-head">
        <h1 class="sf-title">Restricted accounts</h1>
        <p class="sf-lede">Students who are muted or banned right now. Accounts are listed by number, not name. To lift a mute or ban, open the case it came from.</p>
    </header>

    <div class="sf-table">
        @forelse ($accounts as $a)
            <div class="sf-table__row">
                <strong>{{ $a['ref'] }}</strong>
                <span>
                    <b class="{{ $a['status'] === 'Banned' ? 'sf-sev sf-sev--critical' : 'sf-sev sf-sev--high' }}">{{ $a['status'] }}</b>
                    <span class="text-muted">
                        @if ($a['since']) since {{ Staff::when($a['since'], 'M j') }}
                        @elseif ($a['until']) until {{ Staff::when($a['until']) }}
                        @endif
                    </span>
                    <br><span class="text-small text-muted">{{ $a['warnings'] }} {{ $a['warnings'] === 1 ? 'warning' : 'warnings' }}</span>
                </span>
                <span class="text-muted">
                    @if ($a['last_reason'])
                        {{ \App\Services\StaffDashboardService::ACTION_LABELS[$a['last_action']] ?? $a['last_action'] }} by {{ $a['last_by'] ?? 'staff' }}: {{ $a['last_reason'] }}
                    @else
                        No staff note on file.
                    @endif
                </span>
                @if ($a['report_id'])<a href="{{ route('staff.case', $a['report_id']) }}">Open case {{ $a['report_id'] }}</a>@else<span></span>@endif
            </div>
        @empty
            <p class="sf-empty">No one is muted or banned.</p>
        @endforelse
    </div>
@endsection
