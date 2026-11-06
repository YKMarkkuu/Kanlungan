@extends('layouts.community')

@section('back', 'history')
@section('title', 'Post · Kanlungan')
@section('hide-nav', '1')
@section('main-class', $locked ? '' : 'cm-main--composer')

@section('content')
    <div class="stack">
        <header class="cm-header">
            <h1>Post</h1>
        </header>

        @include('components.post-card', ['post' => $post, 'link' => false])

        @if ($hideThread)
            <div class="card cm-empty"><p class="text-muted">Comments are hidden because this post was removed.</p></div>
        @else
            {{-- community.js injects new comments / replies here. --}}
            <div class="cm-comment-list" id="comment-list">
                @foreach ($comments as $comment)
                    @include('components.comment-card', ['comment' => $comment, 'reply' => false, 'isOp' => $comment['is_op'] ?? false, 'canAccept' => $post['mine'] && ! $locked, 'locked' => $locked])
                @endforeach

                @if (count($comments) === 0)
                    <div class="card cm-empty" id="comments-empty"><p class="text-muted">No comments.</p></div>
                @endif
        @endif
    </div>

    @if ($locked)
        <p class="text-small text-muted" style="text-align:center">This post was removed, so new comments are turned off.</p>
    @else
        {{-- Comment box pinned to the bottom (mockup screen 5): [anon] [textarea] [send]. --}}
        <form method="POST" action="{{ route('post.comment', $post['id']) }}" class="cm-composer" id="comment-form" data-alias="{{ $alias }}" data-display-name="{{ trim((string) auth()->user()->display_name) !== '' ? auth()->user()->display_name : $alias }}">
            @csrf
            <input type="hidden" name="parent_id" id="parent-id" value="">
            <input type="hidden" name="anonymous" id="anon-field" value="1">

            <p class="text-small text-muted cm-composer__reply" id="reply-note" hidden>
                <span id="reply-name"></span>
                <button type="button" class="link-btn" id="reply-cancel">Cancel</button>
            </p>
            @error('body') <p class="field__error">{{ $message }}</p> @enderror
            <p class="field__error" id="comment-error" role="alert" aria-live="polite" hidden></p>

            {{-- community.js rewrites this text when the anonymous toggle changes. Keep the id. --}}
            <p class="cm-composer__label" id="comment-hint"></p>

            <div class="cm-composer__row">
                <button type="button" class="cm-icon-btn cm-anon-toggle is-on" id="anon-toggle" aria-pressed="true" aria-label="Comment anonymously" title="Anonymous">@include('components.icon', ['name' => 'mask'])</button>
                <textarea class="input" name="body" id="comment-body" placeholder="Write a comment…" maxlength="1000" rows="1" autocomplete="off" required></textarea>
                <button type="submit" class="btn btn--primary cm-composer__send" aria-label="Send comment" title="Send">@include('components.icon', ['name' => 'send'])</button>
            </div>
        </form>
    @endif
@endsection
