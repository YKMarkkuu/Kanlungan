{{-- One row on the Alerts page (design screen 7). Expects $alert. Older alerts without a snippet fall back to body, then title only. --}}
@php($snippet = $alert['snippet'] ?? null)
@php($body = $alert['body'] ?? null)
<a href="{{ route('alerts.open', $alert['id']) }}" class="card cm-alert cm-alert--rich {{ $alert['unread'] ? 'is-unread' : '' }}">
    <span class="cm-alert__icon cm-alert__icon--{{ $alert['kind'] }}" aria-hidden="true">@include('components.icon', ['name' => $alert['icon'] ?? 'bell'])</span>

    <span class="cm-alert__content">
        <strong class="cm-alert__title">{{ $alert['title'] }}</strong>

        @if ($snippet)
            <span class="cm-alert__snippet">“{{ $snippet }}”</span>
        @elseif ($body)
            <span class="cm-alert__body text-small text-muted">{{ $body }}</span>
        @endif

        <span class="cm-alert__time text-small text-muted">{{ $alert['time'] }}</span>
    </span>

    @if ($alert['unread'])
        <span class="cm-alert__dot" role="img" aria-label="Unread"></span>
    @endif
</a>
