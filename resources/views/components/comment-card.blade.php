{{-- One comment. Expects $comment (see docs/FRONTEND.md "Comment"). $reply = indented one-level reply.
     $canAccept = the viewer wrote the post, so they can mark an answer as Accepted. --}}
@php($reply = $reply ?? false)
@php($canAccept = ($canAccept ?? false) && ! $reply && ! $comment['mine'] && ! $comment['removed'])
@if ($comment['removed'])
    <div class="card cm-comment cm-post--removed {{ $reply ? 'cm-comment--reply' : '' }}" id="comment-{{ $comment['id'] }}">
        <p class="cm-post__body text-muted">{{ $comment['body'] }}</p>
    </div>
@else
<div class="card cm-comment {{ $reply ? 'cm-comment--reply' : '' }} {{ $comment['report'] ? 'cm-reported' : '' }}" id="comment-{{ $comment['id'] }}">
    <div class="cm-post__head">
        <strong class="cm-post__author">{{ $comment['author_alias'] }}</strong>
        @if ($comment['is_accepted'])
            <span class="cm-badge">@include('components.icon', ['name' => 'check']) Accepted</span>
        @endif
        <span class="text-small text-muted">{{ $comment['time_ago'] }}</span>
    </div>

    <p class="cm-post__body">{{ $comment['body'] }}</p>

    @include('components.report-banner', ['item' => $comment])

    <div class="cm-post__foot text-small text-muted">
        {{-- Authors cannot like or mark their own comment helpful: no buttons, just the counts. --}}
        @if ($comment['mine'])
            <span class="cm-stat" aria-label="{{ $comment['likes'] }} likes">@include('components.icon', ['name' => 'like']) {{ $comment['likes'] ?: '' }}</span>
            @unless ($reply)
                @if ($comment['helpful_count'])
                    <span class="cm-stat">@include('components.icon', ['name' => 'star']) Helpful {{ $comment['helpful_count'] }}</span>
                @endif
            @endunless
        @else
            <form method="POST" action="{{ route('comment.like', [$comment['post_id'], $comment['id']]) }}">
                @csrf
                <button type="submit" class="cm-react {{ $comment['liked'] ? 'is-on' : '' }}" aria-pressed="{{ $comment['liked'] ? 'true' : 'false' }}" aria-label="Like">@include('components.icon', ['name' => 'like']) {{ $comment['likes'] ?: '' }}</button>
            </form>
            @unless ($reply)
                <form method="POST" action="{{ route('comment.helpful', [$comment['post_id'], $comment['id']]) }}">
                    @csrf
                    <button type="submit" class="cm-react {{ $comment['helpful_marked'] ? 'is-on' : '' }}" aria-pressed="{{ $comment['helpful_marked'] ? 'true' : 'false' }}">@include('components.icon', ['name' => 'star']) Helpful{{ $comment['helpful_count'] ? ' '.$comment['helpful_count'] : '' }}</button>
                </form>
            @endunless
        @endif
        <button type="button" class="link-btn" data-reply-to="{{ $comment['id'] }}" data-reply-name="{{ $comment['author_alias'] }}">Reply</button>
        @if ($canAccept)
            <form method="POST" action="{{ route('comment.accept', [$comment['post_id'], $comment['id']]) }}">
                @csrf
                <button type="submit" class="link-btn">{{ $comment['is_accepted'] ? 'Un-accept' : 'Accept' }}</button>
            </form>
        @endif
        @include('components.report-flag', ['type' => 'comment', 'item' => $comment])
    </div>
</div>
@endif
