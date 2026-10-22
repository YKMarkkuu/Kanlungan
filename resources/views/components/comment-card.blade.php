{{-- One comment with optional collapsed replies. Expects $comment and optional $reply / $canAccept. --}}
@php($reply = $reply ?? false)
@php($canAccept = ($canAccept ?? false) && ! $reply && ! $comment['mine'] && ! $comment['removed'])
@php($replies = $reply ? [] : ($comment['replies'] ?? []))
@php($repliesId = 'comment-replies-'.$comment['id'])

<div class="cm-comment-wrapper {{ $reply ? 'cm-comment-wrapper--reply' : '' }}">
    <div class="cm-comment {{ $reply ? 'cm-comment--reply' : '' }} {{ $comment['report'] ? 'cm-reported' : '' }} {{ $comment['removed'] ? 'cm-comment--removed' : '' }}" id="comment-{{ $comment['id'] }}">
        <div class="cm-avatar" aria-hidden="true"></div>
        <div class="cm-comment__content">
            <div class="cm-comment__bubble">
                @unless ($comment['removed'])
                    <strong class="cm-comment__author">{{ $comment['author_alias'] }}</strong>
                @endunless
                <p class="cm-comment__body {{ $comment['removed'] ? 'text-muted' : '' }}">{{ $comment['body'] }}</p>
                @unless ($comment['removed'])
                    @if ($comment['is_accepted'])
                        <span class="cm-badge">@include('components.icon', ['name' => 'check']) Accepted</span>
                    @endif
                @endunless
            </div>

            @unless ($comment['removed'])
                @include('components.report-banner', ['item' => $comment])

                <div class="cm-comment__actions">
                    @if ($comment['mine'])
                        <span class="cm-comment__action-count" aria-label="{{ $comment['likes'] }} likes">Like{{ $comment['likes'] ? ' '.$comment['likes'] : '' }}</span>
                        @unless ($reply)
                            @if ($comment['helpful_count'])
                                <span class="cm-comment__action-count">Helpful {{ $comment['helpful_count'] }}</span>
                            @endif
                        @endunless
                    @else
                        <form method="POST" action="{{ route('comment.like', [$comment['post_id'], $comment['id']]) }}">
                            @csrf
                            <button type="submit" class="cm-comment__action-btn {{ $comment['liked'] ? 'is-on' : '' }}" aria-pressed="{{ $comment['liked'] ? 'true' : 'false' }}">Like{{ $comment['likes'] ? ' '.$comment['likes'] : '' }}</button>
                        </form>
                        @unless ($reply)
                            <form method="POST" action="{{ route('comment.helpful', [$comment['post_id'], $comment['id']]) }}">
                                @csrf
                                <button type="submit" class="cm-comment__action-btn {{ $comment['helpful_marked'] ? 'is-on' : '' }}" aria-pressed="{{ $comment['helpful_marked'] ? 'true' : 'false' }}">Helpful{{ $comment['helpful_count'] ? ' '.$comment['helpful_count'] : '' }}</button>
                            </form>
                        @endunless
                    @endif

                    <button type="button" class="cm-comment__action-btn" data-reply-to="{{ $comment['id'] }}" data-reply-name="{{ $comment['author_alias'] }}">Reply</button>

                    @if ($canAccept)
                        <form method="POST" action="{{ route('comment.accept', [$comment['post_id'], $comment['id']]) }}">
                            @csrf
                            <button type="submit" class="cm-comment__action-btn">{{ $comment['is_accepted'] ? 'Un-accept' : 'Accept' }}</button>
                        </form>
                    @endif

                    @include('components.report-flag', ['type' => 'comment', 'item' => $comment])
                    <span class="cm-comment__time">{{ $comment['time_ago'] }}</span>
                </div>
            @endunless
        </div>
    </div>

    @if (count($replies) > 0)
        <button type="button"
                class="cm-view-replies-btn"
                data-toggle-replies="{{ $repliesId }}"
                data-expanded-label="Hide replies"
                aria-controls="{{ $repliesId }}"
                aria-expanded="false">
            View {{ count($replies) }} {{ \Illuminate\Support\Str::plural('reply', count($replies)) }}
        </button>
        <div class="cm-replies-container hidden" id="{{ $repliesId }}">
            @foreach ($replies as $child)
                @include('components.comment-card', ['comment' => $child, 'reply' => true])
            @endforeach
        </div>
    @endif
</div>
