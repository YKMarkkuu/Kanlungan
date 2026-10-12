{{-- One row on the Alerts page (design screen 7). Expects $alert. --}}
<a href="{{ route('alerts.open', $alert['id']) }}" class="card cm-alert {{ $alert['unread'] ? 'is-unread' : '' }}">
    <strong>{{ $alert['title'] }}</strong>
    @if ($alert['body'])
        <span class="text-small text-muted">{{ $alert['body'] }}</span>
    @endif
    <span class="text-small text-muted">{{ $alert['time'] }}</span>
</a>
