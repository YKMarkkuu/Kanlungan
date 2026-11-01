@extends('layouts.community')

@section('title', 'Activity · Kanlungan')
@section('nav', 'alerts')

@section('content')
    <div class="stack" data-activity>
        <header class="cm-header">
            <h1>Activity</h1>
            @if ($alerts->contains('unread', true))
                <form method="POST" action="{{ route('alerts.read-all') }}">
                    @csrf
                    <button type="submit" class="link-btn">Mark all read</button>
                </form>
            @endif
        </header>

        <div class="cm-activity-tabs" role="tablist" aria-label="Activity sections">
            <button type="button" class="cm-activity-tab is-active" id="activity-tab-notifications"
                    role="tab" aria-selected="true" aria-controls="activity-panel-notifications"
                    tabindex="0" data-activity-tab="notifications">My Notifications</button>
            <button type="button" class="cm-activity-tab" id="activity-tab-verdicts"
                    role="tab" aria-selected="false" aria-controls="activity-panel-verdicts"
                    tabindex="-1" data-activity-tab="verdicts">Community Verdicts</button>
        </div>

        <section class="cm-activity-panel" id="activity-panel-notifications" role="tabpanel"
                 aria-labelledby="activity-tab-notifications" data-activity-panel="notifications">
            @forelse ($alerts as $alert)
                @include('components.alert-item', ['alert' => $alert])
            @empty
                <div class="card cm-empty"><p class="text-muted">No alerts yet.</p></div>
            @endforelse
        </section>

        <section class="cm-activity-panel" id="activity-panel-verdicts" role="tabpanel"
                 aria-labelledby="activity-tab-verdicts" data-activity-panel="verdicts" hidden>
            <div class="card cm-notice" role="note">
                Help keep Kanlungan safe. Review these reports and cast your vote.
            </div>
            @include('reports._list', ['reports' => $reportedItems])
        </section>
    </div>
@endsection
