@extends('layouts.community')

@section('title', 'Post · Kanlungan')
@section('hide-nav', '1')
@section('main-class', 'cm-main--composer')

@section('content')
    <div class="stack">
        <header class="cm-header">
            <a href="{{ route('feed') }}" class="cm-back">@include('components.icon', ['name' => 'back']) Post</a>
            <span class="cm-icon-btn is-disabled" title="More options are not built yet">@include('components.icon', ['name' => 'more'])</span>
        </header>

        @include('components.post-card', ['post' => $post, 'link' => false])

        @foreach ($comments as $comment)
            @include('components.comment-card', ['comment' => $comment, 'reply' => false, 'canAccept' => $post['mine']])

            @foreach ($comment['replies'] as $child)
                @include('components.comment-card', ['comment' => $child, 'reply' => true])
            @endforeach
        @endforeach

        @if (count($comments) === 0)
            <div class="card cm-empty"><p class="text-muted">No comments yet. Start the conversation.</p></div>
        @endif
    </div>

    {{-- Comment box pinned to the bottom (mockup screen 5). --}}
    <form method="POST" action="{{ route('post.comment', $post['id']) }}" class="cm-composer" id="comment-form" data-alias="{{ $alias }}">
        @csrf
        <input type="hidden" name="parent_id" id="parent-id" value="">
        <input type="hidden" name="anonymous" id="anon-field" value="1">

        <p class="text-small text-muted cm-composer__reply" id="reply-note" hidden>
            <span id="reply-name"></span>
            <button type="button" class="link-btn" id="reply-cancel">Cancel</button>
        </p>
        @error('body') <p class="field__error">{{ $message }}</p> @enderror

        <div class="cm-composer__row">
            <input class="input" type="text" name="body" id="comment-body" placeholder="Write a comment…" maxlength="1000" autocomplete="off" required>
            <button type="button" class="cm-icon-btn cm-anon-toggle is-on" id="anon-toggle" aria-pressed="true" aria-label="Comment anonymously" title="Anonymous">@include('components.icon', ['name' => 'mask'])</button>
            <button type="submit" class="btn btn--primary cm-send">Send</button>
        </div>
        <p class="text-small text-muted" id="comment-hint">Commenting as {{ $alias }}</p>
    </form>
@endsection
