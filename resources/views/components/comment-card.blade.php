{{-- One comment with optional collapsed replies. Expects $comment and optional $reply / $canAccept. --}}
@php($reply = $reply ?? false)
@php($canAccept = ($canAccept ?? false) && ! $reply && ! $comment['mine'] && ! $comment['removed'])
@php($replies = $reply ? [] : ($comment['replies'] ?? []))
@php($repliesId = 'comment-replies-'.$comment['id'])
@php($menuId = 'comment-actions-'.$comment['id'])

<div class="cm-comment-wrapper {{ $reply ? 'cm-comment-wrapper--reply' : '' }}">
    <div class="cm-comment {{ $reply ? 'cm-comment--reply' : '' }} {{ ($comment['report'] && ! $comment['mine']) ? 'cm-reported' : '' }} {{ $comment['removed'] ? 'cm-comment--removed' : '' }}" id="comment-{{ $comment['id'] }}">
        <x-avatar :avatar-path="$comment['avatar_path'] ?? null" :name="$comment['author_alias']" />
        <div class="cm-comment__content">
            <div class="cm-comment__bubble">
                <div class="cm-comment__head">
                    @unless ($comment['removed'])
                        <strong class="cm-comment__author">{{ $comment['author_alias'] }}</strong>
                    @endunless
                    <time class="cm-comment__time" title="{{ $comment['created_at']->format('F j, Y, g:i A') }}">{{ $comment['time_ago'] }}</time>
                    @if ($canAccept)
                        <form method="POST" action="{{ route('comment.accept', [$comment['post_id'], $comment['id']]) }}">
                            @csrf
                            <button type="submit"
                                    class="cm-comment__accept {{ $comment['is_accepted'] ? 'is-active' : '' }}"
                                    aria-label="{{ $comment['is_accepted'] ? 'Unaccept answer' : 'Accept answer' }}"
                                    title="{{ $comment['is_accepted'] ? 'Unaccept answer' : 'Accept answer' }}">
                                @include('components.icon', ['name' => 'check'])
                                <span>{{ $comment['is_accepted'] ? 'Un-accept' : 'Accept' }}</span>
                            </button>
                        </form>
                    @endif
                </div>

                <p class="cm-comment__body {{ $comment['removed'] ? 'text-muted' : '' }}">{{ $comment['body'] }}</p>
                @unless ($comment['removed'])
                    @if ($comment['is_accepted'])
                        <span class="cm-badge">@include('components.icon', ['name' => 'check']) Accepted</span>
                    @endif
                @endunless
            </div>

            @unless ($comment['removed'])
                @if (! $comment['mine'])
                    @include('components.report-banner', ['item' => $comment])
                @endif

                <div class="cm-comment__actions">
                    <div class="cm-comment__actions-left">
                        <button type="button" class="cm-comment__action-btn" data-reply-to="{{ $comment['id'] }}" data-reply-name="{{ $comment['author_alias'] }}" aria-label="Reply to comment" title="Reply to comment">Reply</button>
                        @if (count($replies) > 0)
                            <button type="button"
                                    class="cm-view-replies-btn"
                                    data-toggle-replies="{{ $repliesId }}"
                                    data-expanded-label="Hide replies"
                                    aria-controls="{{ $repliesId }}"
                                    aria-expanded="false">
                                View {{ count($replies) }} {{ \Illuminate\Support\Str::plural('reply', count($replies)) }}
                            </button>
                        @endif
                        <span class="cm-comment__time" title="{{ $comment['created_at']->format('F j, Y, g:i A') }}">{{ $comment['time_ago'] }}</span>
                    </div>

                    <div class="cm-comment__actions-right">
                        @if ($comment['mine'])
                            <span class="cm-comment__action-count cm-comment__action-btn" aria-label="{{ $comment['likes'] }} likes" title="{{ $comment['likes'] }} likes">
                                @include('components.icon', ['name' => 'like'])
                                <span>Like</span><span class="cm-stat__count" data-hide-zero>{{ $comment['likes'] ?: '' }}</span>
                            </span>
                            @if ($comment['helpful_count'])
                                <span class="cm-comment__action-count" aria-label="{{ $comment['helpful_count'] }} helpful marks" title="{{ $comment['helpful_count'] }} helpful marks">
                                    @include('components.icon', ['name' => 'star'])
                                    <span>Helpful</span><span class="cm-stat__count">{{ $comment['helpful_count'] }}</span>
                                </span>
                            @endif
                        @else
                            <form method="POST" action="{{ route('comment.like', [$comment['post_id'], $comment['id']]) }}">
                                @csrf
                                <button type="submit"
                                        class="cm-comment__action-btn cm-comment__reaction {{ $comment['liked'] ? 'is-active' : '' }}"
                                        data-react="like"
                                        data-comment-id="{{ $comment['id'] }}"
                                        aria-pressed="{{ $comment['liked'] ? 'true' : 'false' }}"
                                        aria-label="{{ $comment['liked'] ? 'Unlike' : 'Like' }} comment, {{ $comment['likes'] }} likes"
                                        title="{{ $comment['liked'] ? 'Unlike comment' : 'Like comment' }}">
                                    @include('components.icon', ['name' => 'like'])
                                    <span>Like</span><span class="cm-stat__count" data-hide-zero>{{ $comment['likes'] ?: '' }}</span>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('comment.helpful', [$comment['post_id'], $comment['id']]) }}">
                                @csrf
                                <button type="submit"
                                        class="cm-comment__action-btn cm-comment__reaction cm-comment__reaction--helpful {{ $comment['helpful_marked'] ? 'is-active' : '' }}"
                                        data-react="helpful"
                                        data-comment-id="{{ $comment['id'] }}"
                                        aria-pressed="{{ $comment['helpful_marked'] ? 'true' : 'false' }}"
                                        aria-label="{{ $comment['helpful_marked'] ? 'Remove helpful mark' : 'Mark helpful' }}, {{ $comment['helpful_count'] }} helpful marks"
                                        title="{{ $comment['helpful_marked'] ? 'Remove helpful mark' : 'Mark helpful' }}">
                                    @include('components.icon', ['name' => 'star'])
                                    <span>Helpful</span><span class="cm-stat__count" data-hide-zero>{{ $comment['helpful_count'] ?: '' }}</span>
                                </button>
                            </form>
                        @endif

                        <div class="cm-more-menu cm-comment__action-menu">
                            <button type="button" class="cm-comment__action-btn cm-more-menu__toggle"
                                    data-menu-toggle aria-label="More comment actions" title="More actions"
                                    aria-expanded="false" aria-controls="{{ $menuId }}">
                                @include('components.icon', ['name' => 'more'])
                            </button>
                            <div class="cm-more-menu__dropdown hidden" id="{{ $menuId }}">
                                @if ($comment['mine'])
                                    <form method="POST"
                                          action="{{ route('comment.destroy', [$comment['post_id'], $comment['id']]) }}"
                                          onsubmit="return confirm('Delete this comment?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="cm-menu-item text-danger">Delete</button>
                                    </form>
                                @else
                                    @include('components.report-flag', ['type' => 'comment', 'item' => $comment, 'showLabel' => true])
                                    @include('components.block-button', ['type' => 'comment', 'item' => $comment, 'menuItem' => true])
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endunless
        </div>
    </div>

    @if (count($replies) > 0)
        <div class="cm-replies-container hidden" id="{{ $repliesId }}">
            @foreach ($replies as $child)
                @include('components.comment-card', ['comment' => $child, 'reply' => true])
            @endforeach
        </div>
    @endif

</div>
