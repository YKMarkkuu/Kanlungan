{{-- Feed post card. Expects $post (see docs/FRONTEND.md "Feed post"). $link = make the body a link to the thread. --}}
@php($link = $link ?? true)
@if ($post['removed'])
    <article class="card cm-post cm-post--removed" id="post-{{ $post['id'] }}">
        <p class="cm-post__body text-muted">{{ $post['body'] }}</p>
        @if ($link)
            <a class="text-small cm-underline" href="{{ route('post.show', $post['id']) }}">Open thread</a>
        @endif
    </article>
@else
<article class="card cm-post {{ $post['report'] ? 'cm-reported' : '' }}" id="post-{{ $post['id'] }}">
    <div class="cm-post__head">
        <div class="cm-avatar" aria-hidden="true"></div>
        <strong class="cm-post__author">{{ $post['author_alias'] }}</strong>
        <span class="text-small text-muted">{{ $post['time_ago'] }}</span>
    </div>

    <span class="chip">{{ $post['category'] }}</span>

    @if ($link)
        <a class="cm-post__body cm-post__link" href="{{ route('post.show', $post['id']) }}">{{ $post['body'] }}</a>
    @else
        <p class="cm-post__body">{{ $post['body'] }}</p>
    @endif

    @if ($post['image'])
        <img class="cm-post__img" src="{{ asset($post['image']) }}" alt="Photo attached to this post" loading="lazy">
    @endif

    @include('components.report-banner', ['item' => $post])

    <div class="cm-post__foot text-small text-muted">
        {{-- Authors cannot like their own post: no button, just the count. --}}
        @if ($post['mine'])
            <span class="cm-stat" aria-label="{{ $post['likes'] }} likes">@include('components.icon', ['name' => 'like']) {{ $post['likes'] }}</span>
        @else
            <form method="POST" action="{{ route('post.like', $post['id']) }}">
                @csrf
                <button type="submit" class="cm-react {{ $post['liked'] ? 'is-on' : '' }}" aria-pressed="{{ $post['liked'] ? 'true' : 'false' }}" aria-label="Like">@include('components.icon', ['name' => 'like']) {{ $post['likes'] }}</button>
            </form>
        @endif
        @if ($link)
            <a class="cm-stat" href="{{ route('post.show', $post['id']) }}" aria-label="Open comments">@include('components.icon', ['name' => 'comment']) {{ $post['comments'] }}</a>
        @else
            <span class="cm-stat">@include('components.icon', ['name' => 'comment']) {{ $post['comments'] }}</span>
        @endif
        @include('components.report-flag', ['type' => 'post', 'item' => $post])
    </div>
</article>
@endif
