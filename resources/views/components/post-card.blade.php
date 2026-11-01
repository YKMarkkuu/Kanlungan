{{-- Feed post card. Expects $post (see docs/FRONTEND.md "Feed post"). $link = make the body a link to the thread. --}}
@php($link = $link ?? true)
@php($showReportStatus = $showReportStatus ?? true)
@php($menuId = 'post-actions-'.$post['id'])
@if ($post['removed'])
    <article class="card cm-post cm-post--removed" id="post-{{ $post['id'] }}">
        <p class="cm-post__body text-muted">{{ $post['body'] }}</p>
        @if ($link)
            <a class="text-small cm-underline" href="{{ route('post.show', $post['id']) }}">Open thread</a>
        @endif
    </article>
@else
<article class="card cm-post {{ $showReportStatus && $post['report'] ? 'cm-reported' : '' }}" id="post-{{ $post['id'] }}">
    <div class="cm-post__head">
        <x-avatar :avatar-path="$post['avatar_path'] ?? null" :name="$post['author_alias']" />
        <strong class="cm-post__author">{{ $post['author_alias'] }}</strong>
        <span class="text-small text-muted" title="{{ $post['created_at']->format('F j, Y, g:i A') }}">{{ $post['time_ago'] }}</span>
    </div>

    <span class="chip">{{ $post['category'] }}</span>

    {{-- Feed cards clamp to 6 lines; the thread page ($link = false) shows everything. --}}
    <div class="cm-post__text" @if ($link) data-clamp-wrap @endif>
        @if ($link)
            <a class="cm-post__body cm-post__link cm-clamp" href="{{ route('post.show', $post['id']) }}">{{ $post['body'] }}</a>
        @else
            <p class="cm-post__body">{{ $post['body'] }}</p>
        @endif

        @if ($link)
            <button type="button" class="link-btn cm-see-more" data-see-more aria-expanded="false" hidden>See more</button>
        @endif
    </div>

    @if ($post['image'])
        <img class="cm-post__img"
             src="{{ asset($post['image']) }}"
             data-lightbox-src="{{ asset($post['image']) }}"
             alt="Photo attached to this post"
             loading="lazy">
    @endif

    @if ($showReportStatus)
        @include('components.report-banner', ['item' => $post])
    @endif

    <div class="cm-post__foot text-small text-muted">
        {{-- Authors cannot like their own post: no button, just the count. --}}
        @if ($post['mine'])
            <span class="cm-action-btn cm-action-count" aria-label="{{ $post['likes'] }} likes" title="{{ $post['likes'] }} likes">@include('components.icon', ['name' => 'like']) <span class="cm-stat__count">{{ $post['likes'] }}</span></span>
        @else
            {{-- Plain form = no-JS fallback. community.js intercepts the button click (data-react). --}}
            <form method="POST" action="{{ route('post.like', $post['id']) }}">
                @csrf
                <button type="submit"
                        class="cm-action-btn cm-react {{ $post['liked'] ? 'is-active' : '' }}"
                        data-react="like"
                        data-post-id="{{ $post['id'] }}"
                        aria-pressed="{{ $post['liked'] ? 'true' : 'false' }}"
                        aria-label="{{ $post['liked'] ? 'Unlike' : 'Like' }} post, {{ $post['likes'] }} likes"
                        title="{{ $post['liked'] ? 'Unlike post' : 'Like post' }}">@include('components.icon', ['name' => 'like'])<span class="cm-stat__count">{{ $post['likes'] }}</span></button>
            </form>
        @endif
        @if ($link)
            <a class="cm-action-btn cm-stat" href="{{ route('post.show', $post['id']) }}" aria-label="Comment, {{ $post['comments'] }} comments" title="Comment">@include('components.icon', ['name' => 'comment'])<span class="cm-stat__count" data-comments-count>{{ $post['comments'] }}</span></a>
        @else
            <span class="cm-action-btn cm-stat" aria-label="Comment, {{ $post['comments'] }} comments" title="Comment">@include('components.icon', ['name' => 'comment'])<span class="cm-stat__count" data-comments-count>{{ $post['comments'] }}</span></span>
        @endif
        <button type="button" class="cm-action-btn" onclick="copyLink('{{ route('post.show', $post['id']) }}')" aria-label="Share" title="Share">@include('components.icon', ['name' => 'share'])</button>
        <div class="cm-more-menu">
            <button type="button" class="cm-action-btn cm-more-menu__toggle" data-menu-toggle
                    aria-label="More post actions" title="More actions" aria-expanded="false"
                    aria-controls="{{ $menuId }}">
                @include('components.icon', ['name' => 'more'])
            </button>
            <div class="cm-more-menu__dropdown hidden" id="{{ $menuId }}">
                @if ($post['mine'])
                    <form method="POST" action="{{ route('post.destroy', $post['id']) }}"
                          onsubmit="return confirm('Delete this post?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="cm-menu-item text-danger">Delete</button>
                    </form>
                @else
                    @include('components.report-flag', ['type' => 'post', 'item' => $post, 'showLabel' => true])
                    @include('components.block-button', ['type' => 'post', 'item' => $post, 'menuItem' => true])
                @endif
            </div>
        </div>
    </div>
</article>
@endif
