<div class="cm-notification-menu" data-notification-menu>
    <button type="button"
            class="cm-icon-btn cm-bell"
            data-notification-toggle
            data-unread-count="{{ $unread }}"
            data-unread-count-url="{{ route('alerts.unread-count') }}"
            aria-label="Notifications{{ $unread > 0 ? ', '.$unread.' unread' : '' }}"
            aria-expanded="false"
            aria-controls="notification-dropdown">
        @include('components.icon', ['name' => 'bell'])
        <span class="cm-count" data-unread-badge @if ($unread === 0) hidden @endif>{{ $unread }}</span>
    </button>

    <div class="cm-notification-dropdown hidden" id="notification-dropdown" data-notification-dropdown>
        <div class="cm-notification-dropdown__header">
            <strong>Notifications</strong>
            @if ($unread > 0)
                <form method="POST" action="{{ route('alerts.read-all') }}">
                    @csrf
                    <button type="submit" class="cm-notification-dropdown__mark-read">Mark all as read</button>
                </form>
            @endif
        </div>
        <div class="cm-notification-dropdown__list">
            @forelse ($alerts as $alert)
                <a href="{{ route('alerts.open', $alert->id) }}"
                   class="cm-notification-dropdown__item {{ $alert->read_at === null ? 'is-unread' : '' }}">
                    <strong>{{ $alert->title }}</strong>
                   {{-- Keep report/moderator excerpts in Activity instead of exposing removed content across every page. --}}
                   @if (in_array($alert->kind, ['comment', 'helpful'], true) && $alert->snippet)
                       <span class="cm-notification-dropdown__snippet">“{{ $alert->snippet }}”</span>
                   @elseif ($alert->body)
                       <span>{{ $alert->body }}</span>
                   @endif
                    <time datetime="{{ $alert->created_at?->toIso8601String() }}">
                        {{ $alert->created_at?->diffForHumans() }}
                    </time>
                </a>
            @empty
                <p class="cm-notification-dropdown__empty">No notifications yet.</p>
            @endforelse
        </div>
        <a href="{{ route('alerts') }}" class="cm-notification-dropdown__view-all">View all activity</a>
    </div>
</div>
