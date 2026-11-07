@extends('layouts.community')

@section('title', 'Activity · Kanlungan')
@section('nav', 'alerts')

@section('content')
    <div class="stack" data-activity data-session-tab="{{ session('tab', old('tab', '')) }}" data-query-tab="{{ request()->query('tab', '') }}">
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
            <button type="button" class="cm-activity-tab" id="activity-tab-reports"
                    role="tab" aria-selected="false" aria-controls="activity-panel-reports"
                    tabindex="-1" data-activity-tab="reports">My Reports</button>
        </div>

        <section class="cm-activity-panel" id="activity-panel-notifications" role="tabpanel"
                 aria-labelledby="activity-tab-notifications" data-activity-panel="notifications">
            @if ($alerts->isEmpty())
                <x-empty-state icon="bell" title="No notifications yet" message="Comments, helpful marks and moderator updates will show up here." />
            @else
                <div class="cm-alert-list">
                    @foreach ($alerts as $alert)
                        @include('components.alert-item', ['alert' => $alert])
                    @endforeach
                </div>
            @endif
        </section>

        <section class="cm-activity-panel" id="activity-panel-verdicts" role="tabpanel"
                 aria-labelledby="activity-tab-verdicts" data-activity-panel="verdicts" hidden>
            <div class="card cm-notice" role="note">
                Help keep Kanlungan safe. Review these reports and cast your vote.
            </div>
            @include('reports._list', ['reports' => $reportedItems])
        </section>

        <section class="cm-activity-panel" id="activity-panel-reports" role="tabpanel"
                 aria-labelledby="activity-tab-reports" data-activity-panel="reports" hidden>
            @forelse ($myReports as $report)
                <article class="card cm-my-report">
                    <p class="cm-my-report__preview">{{ $report['preview'] }}</p>
                    <div class="cm-my-report__meta">
                        <time class="text-small text-muted">{{ $report['reported_at'] }}</time>
                        <span class="cm-report-status cm-report-status--{{ $report['status_class'] }}">
                            {{ $report['status'] }}
                        </span>
                    </div>
                </article>
            @empty
                <div class="card cm-empty"><p class="text-muted">You haven't reported any content.</p></div>
            @endforelse
        </section>
    </div>
@endsection
