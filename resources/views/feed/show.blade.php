@extends('layouts.community')

@section('back', 'history')
@section('title', 'Post · Kanlungan')
@section('hide-nav', '1')
@section('main-class', 'cm-main--composer')

@section('content')
    <div class="stack">
        <header class="cm-header">
            <h1>Post</h1>
            <span class="cm-icon-btn is-disabled" title="More options are not built yet">@include('components.icon', ['name' => 'more'])</span>
        </header>

        @include('components.post-card', ['post' => $post, 'link' => false])

        {{-- community.js injects new comments / replies here. --}}
        <div class="cm-comment-list" id="comment-list">
            @foreach ($comments as $comment)
                @include('components.comment-card', ['comment' => $comment, 'reply' => false, 'canAccept' => $post['mine']])
            @endforeach

            @if (count($comments) === 0)
                <div class="card cm-empty" id="comments-empty"><p class="text-muted">No comments yet. Start the conversation.</p></div>
            @endif
        </div>
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
        <p class="field__error" id="comment-error" role="alert" aria-live="polite" hidden></p>

        <div class="cm-composer__row">
            <input class="input" type="text" name="body" id="comment-body" placeholder="Write a comment…" maxlength="1000" autocomplete="off" required>
            <button type="button" class="cm-icon-btn cm-anon-toggle is-on" id="anon-toggle" aria-pressed="true" aria-label="Comment anonymously" title="Anonymous">@include('components.icon', ['name' => 'mask'])</button>
            <button type="submit" class="btn btn--primary cm-send">Send</button>
        </div>
        <p class="text-small text-muted" id="comment-hint">Commenting as {{ $alias }}</p>
    </form>
@endsection
