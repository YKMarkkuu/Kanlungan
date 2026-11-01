{{-- Muted report status + vote controls under a reported post/comment. Expects $item (post/comment array). --}}
@php($report = $item['report'])
@if (! $item['mine'] && $report && ! ($report['is_reporter'] ?? false) && ! ($report['is_author'] ?? false))
    <div class="cm-report-banner" role="status">
        @if ($report['status'] === 'open')
            <p class="text-small">
                <span class="cm-report-banner__label">
                    @include('components.icon', ['name' => 'flag'])
                    <strong>Reported: {{ $report['category'] }}</strong>
                </span>
            </p>
            @if ($report['can_start'])
                <form method="POST" action="{{ route('report.start', $report['id']) }}">
                    @csrf
                    <button type="submit" class="btn">Start voting</button>
                </form>
            @elseif ($report['is_reporter'])
                <p class="text-small text-muted">You reported this. Another student can start the vote.</p>
            @else
                <p class="text-small text-muted">Waiting for students to start a vote.</p>
            @endif
        @else
            <p class="text-small">
                <span class="cm-report-banner__label">
                    @include('components.icon', ['name' => 'flag'])
                    <strong>Vote: is this {{ $report['category'] }}?</strong>
                </span>
                <span class="text-muted">{{ $report['votes'] }}/{{ $report['quorum'] }} votes · ends in {{ $report['ends_in'] }}</span>
            </p>
            @if ($report['can_vote'])
                <form method="POST" action="{{ route('report.vote', $report['id']) }}" class="cm-report-banner__votes">
                    @csrf
                    <button type="submit" name="choice" value="agree" class="btn">Agree</button>
                    <button type="submit" name="choice" value="false_report" class="btn">False report</button>
                    <button type="submit" name="choice" value="wrong_tag" class="btn">Wrong tag</button>
                </form>
            @elseif ($report['has_voted'])
                <p class="text-small text-muted">You voted. Results show when the vote closes.</p>
            @else
                <p class="text-small text-muted">You can't vote on your own post/comment or report.</p>
            @endif
        @endif
    </div>
@endif
