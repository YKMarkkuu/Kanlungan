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
@php($reported = $showReportStatus && $post['report'])
@php($isMarket = $post['is_marketplace'] ?? false)
@php($isMeme = $post['is_meme'] ?? false)
@php($bookmarked = $post['has_bookmarked'] ?? false)
<article class="card cm-post {{ $reported ? 'cm-reported' : 'cm-post--flat' }} {{ $isMeme ? 'cm-post--meme' : '' }}" id="post-{{ $post['id'] }}">
    <div class="cm-post__head">
        <x-avatar :avatar-path="$post['avatar_path'] ?? null" :name="$post['author_alias']" />
        <div class="cm-post__meta">
            <strong class="cm-post__author">{{ $post['author_alias'] }}</strong>
            <time class="cm-post__time" datetime="{{ $post['created_at']->toIso8601String() }}" title="{{ $post['created_at']->format('F j, Y, g:i A') }}">{{ $post['time_ago'] }}</time>
            <span class="chip--tag">
                @if ($isMeme)@include('components.icon', ['name' => 'smile'])@endif
                @if ($isMarket)@include('components.icon', ['name' => 'tag'])@endif
                {{ $post['category'] }}
            </span>
            @if ($isMarket && ($post['price_text'] ?? null))
                <span class="cm-price" aria-label="{{ $post['price_label'] }}">{{ $post['price_text'] }}</span>
                @if (($post['item_count'] ?? 0) > 1)
                    <span class="cm-post__condition">{{ $post['item_count'] }} items</span>
                @endif
                @if ($post['condition'])
                    <span class="cm-post__condition">{{ $post['condition'] }}</span>
                @endif
            @endif
        </div>
        <div class="cm-more-menu">
            <button type="button" class="cm-action-btn cm-more-menu__toggle" data-menu-toggle
                    aria-label="More post actions" title="More actions" aria-expanded="false"
                    aria-controls="{{ $menuId }}">
                @include('components.icon', ['name' => 'more'])
            </button>
            <div class="cm-more-menu__dropdown hidden" id="{{ $menuId }}">
                @if ($post['mine'])
                    <form method="POST" action="{{ route('post.destroy', $post['id']) }}"
                          data-confirm-submit data-confirm-destructive
                          data-confirm-title="Delete this post?"
                          data-confirm-message="This action cannot be undone.">
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

    @if (! $link && $isMarket && ($post['item_count'] ?? 0) > 1)
        <ul class="cm-items" aria-label="Items for sale">
            @foreach ($post['items'] as $item)
                <li class="cm-items__row">
                    <span class="cm-items__label">{{ $item['label'] }}</span>
                    <span class="cm-items__price">{{ $item['price'] === 0 ? 'Free' : '₱'.number_format($item['price']) }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    @php($photos = array_slice($post['images'] ?? [], 0, 3))
    @php($photoCount = count($photos))
    @php($first = $photos[0] ?? null)
    @php($ratio = ($first && ($first['width'] ?? 0) > 0 && ($first['height'] ?? 0) > 0) ? max(0.8, min(1.91, $first['width'] / $first['height'])) : 1.333)
    @php($photoStyle = $photoCount === 1 ? '--cm-ar: '.number_format($ratio, 3, '.', '') : '')
    @if ($photoCount > 0)
        <div class="cm-photos cm-photos--{{ $photoCount }}" style="{{ $photoStyle }}">
            @foreach ($photos as $index => $photo)
                <img class="cm-post__img"
                     src="{{ asset($photo['path']) }}"
                     data-lightbox-src="{{ asset($photo['path']) }}"
                     alt="Photo {{ $index + 1 }} of {{ $photoCount }} attached to this post"
                     loading="lazy">
            @endforeach
        </div>
    @endif

    @if ($showReportStatus)
        @include('components.report-banner', ['item' => $post])
    @endif

    <div class="cm-post__foot text-small text-muted">
        @if ($link)
            <a class="cm-post__foot-link" href="{{ route('post.show', $post['id']) }}" aria-label="Open post"></a>
        @endif
        <div class="cm-post__foot-actions">
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
        </div>
        <form method="POST" action="{{ route('post.bookmark', $post['id']) }}" class="cm-bookmark-form">
            @csrf
            <button type="submit"
                    class="cm-action-btn cm-bookmark {{ $bookmarked ? 'is-active' : '' }}"
                    data-bookmark
                    data-post-id="{{ $post['id'] }}"
                    aria-pressed="{{ $bookmarked ? 'true' : 'false' }}"
                    aria-label="{{ $bookmarked ? 'Remove from saved' : 'Save post' }}"
                    title="{{ $bookmarked ? 'Remove from saved' : 'Save post' }}">@include('components.icon', ['name' => 'bookmark'])</button>
        </form>
    </div>
</article>
@endif
