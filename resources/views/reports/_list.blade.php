@forelse ($reports as $row)
    @if ($row['kind'] === 'post')
        @include('components.post-card', ['post' => $row['item']])
    @else
        @include('components.comment-card', ['comment' => $row['item'], 'reply' => false, 'canAccept' => false])
    @endif
@empty
    <div class="card cm-empty">
        <p><strong>No reports need your verdict right now.</strong></p>
        <p class="text-small text-muted">Check back later to help keep the community safe.</p>
    </div>
@endforelse
