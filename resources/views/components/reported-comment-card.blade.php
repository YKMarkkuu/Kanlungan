{{-- A reported COMMENT shown in the feed's "Needs your vote" section. Expects $comment. --}}
<article class="card cm-post cm-reported" id="comment-{{ $comment['id'] }}">
    <div class="cm-post__head">
        <div class="cm-avatar" aria-hidden="true"></div>
        <strong class="cm-post__author">{{ $comment['author_alias'] }}</strong>
        <span class="chip">Comment</span>
        <span class="text-small text-muted">{{ $comment['time_ago'] }}</span>
    </div>

    <a class="cm-post__body cm-post__link" href="{{ route('post.show', $comment['post_id']) }}#comment-{{ $comment['id'] }}">{{ $comment['body'] }}</a>
    @if ($comment['post_excerpt'])
        <p class="text-small text-muted">On: {{ $comment['post_excerpt'] }}</p>
    @endif

    @include('components.report-banner', ['item' => $comment])

    <div class="cm-post__foot text-small text-muted">
        <a class="cm-stat cm-underline" href="{{ route('post.show', $comment['post_id']) }}#comment-{{ $comment['id'] }}">See in thread</a>
        @include('components.report-flag', ['type' => 'comment', 'item' => $comment])
    </div>
</article>
