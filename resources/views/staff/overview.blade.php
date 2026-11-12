@extends('layouts.staff')

@section('title', 'Overview · Kanlungan staff')

@php
    use App\Services\StaffDashboardService as Staff;

    $waitingCount = $focus === 'referred' ? $counts['referred'] : $counts['review'];
    if ($focus === 'referred') {
        $headline = $waitingCount === 0
            ? 'No cases are waiting for Guidance.'
            : ($waitingCount === 1 ? '1 case was referred to Guidance.' : $waitingCount.' cases were referred to Guidance.');
    } else {
        $headline = $waitingCount === 0
            ? 'Nothing is waiting for a decision.'
            : ($waitingCount === 1 ? '1 report needs a decision.' : $waitingCount.' reports need a decision.');
        if ($counts['critical'] > 0) {
            $headline .= ' '.($counts['critical'] === 1 ? '1 is critical.' : $counts['critical'].' are critical.');
        }
    }
@endphp

@section('content')
    <header class="sf-head">
        <h1 class="sf-title">{{ $headline }}</h1>
        <p class="sf-lede">You only see posts and comments that students reported. Names stay hidden unless you reveal one, and every reveal is saved in the audit log.</p>
    </header>

    <nav class="sf-lanes" aria-label="Queues">
        <a class="sf-lane" href="{{ route('staff.reports', ['view' => 'review']) }}"><b>{{ $counts['review'] }}</b><span>Need a decision</span></a>
        <a class="sf-lane" href="{{ route('staff.reports', ['view' => 'referred']) }}"><b>{{ $counts['referred'] }}</b><span>Referred to Guidance</span></a>
        <a class="sf-lane" href="{{ route('staff.reports', ['view' => 'voting']) }}"><b>{{ $counts['voting'] }}</b><span>In community vote</span></a>
        <a class="sf-lane" href="{{ route('staff.sanctions') }}"><b>{{ $counts['sanctions'] }}</b><span>Restricted accounts</span></a>
    </nav>

    <section class="sf-section">
        <h2>{{ $focus === 'referred' ? 'Referred to Guidance' : 'Waiting for a decision' }}</h2>
        @if ($waiting->isEmpty())
            <div class="sf-list"><p class="sf-empty">All clear. New reports appear here as students send them.</p></div>
        @else
            <div class="sf-list">
                @foreach ($waiting as $r)
                    @include('staff._row', ['r' => $r])
                @endforeach
            </div>
            <p class="text-small text-muted" style="margin-top: 8px;">Most serious first, then the longest-waiting. <a href="{{ route('staff.reports', ['view' => $focus]) }}">See all</a></p>
        @endif
    </section>

    <div class="sf-two sf-section">
        <section>
            <h2>Reports by reason, last 30 days</h2>
            @if ($reasons->isEmpty())
                <p class="text-muted">No reports in the last 30 days.</p>
            @else
                <div class="sf-bars">
                    @foreach ($reasons as $reason)
                        <div class="sf-bar">
                            <span>{{ $reason['category'] }}</span>
                            <span class="sf-bar__track"><span class="sf-bar__fill sf-bar__fill--{{ $reason['severity'] }}" style="width: {{ round($reason['total'] / $reasonMax * 100) }}%"></span></span>
                            <b>{{ $reason['total'] }}</b>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section>
            <h2>Recent staff activity</h2>
            @if ($recent->isEmpty())
                <p class="text-muted">No actions yet.</p>
            @else
                <ul class="sf-log">
                    @foreach ($recent as $a)
                        <li>
                            <strong>{{ $a['label'] }}</strong>
                            <span class="text-muted">by {{ $a['by'] }}, {{ Staff::when($a['at']) }}</span>
                            @if ($a['report_id'])<a href="{{ route('staff.case', $a['report_id']) }}">Case {{ $a['report_id'] }}</a>@endif
                        </li>
                    @endforeach
                </ul>
                <p class="text-small" style="margin-top: 8px;"><a href="{{ route('staff.activity') }}">Open the audit log</a></p>
            @endif
        </section>
    </div>
@endsection
