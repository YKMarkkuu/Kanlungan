{{-- One report in a list. $r comes from StaffDashboardService::row(). Whole row is the link. --}}
<a href="{{ route('staff.case', $r['id']) }}" class="sf-row sf-row--{{ $r['severity'] }}">
    <span class="sf-row__stripe" aria-hidden="true"></span>
    <span class="sf-row__body">
        <span class="sf-row__top">
            <span class="sf-sev sf-sev--{{ $r['severity'] }}">{{ ucfirst($r['severity']) }}</span>
            <strong>{{ $r['about_reporter'] ? 'Possible false report' : $r['category'] }} on a {{ $r['kind'] }}</strong>
            @if ($r['referred'])<span class="sf-tag sf-tag--guidance">Referred to Guidance</span>@endif
            <span class="sf-tag">{{ $r['stage'] }}</span>
            @if ($r['removed'])<span class="sf-tag">Removed</span>@endif
        </span>
        <span class="sf-row__excerpt">{{ $r['excerpt'] }}</span>
        <span class="sf-row__meta">
            <span>Reported {{ $r['age'] }} ago</span>
            @if ($r['votes'] > 0)<span>{{ $r['agree'] }} of {{ $r['votes'] }} voters agree</span>@endif
        </span>
    </span>
</a>
