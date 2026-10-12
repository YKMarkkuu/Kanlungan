@extends('layouts.community')

@section('title', 'New post · Kanlungan')
@section('hide-nav', '1')

@section('content')
    <form method="POST" action="{{ route('post.store') }}" class="stack--lg stack" id="post-form" enctype="multipart/form-data" data-alias="{{ $alias }}">
        @csrf

        <header class="cm-header">
            <a href="{{ route('feed') }}" class="cm-icon-btn" aria-label="Cancel">@include('components.icon', ['name' => 'close'])</a>
            <h1 class="cm-header__title">New post</h1>
            <button type="submit" class="btn btn--primary cm-post-btn" id="post-btn" disabled>Post</button>
        </header>

        <fieldset class="cm-chips cm-fieldset" aria-label="Category">
            @foreach ($categories as $category)
                <label>
                    <input type="radio" name="category" value="{{ $category }}" class="cm-visually-hidden"
                        {{ old('category', 'Share') === $category ? 'checked' : '' }}>
                    <span class="chip">{{ $category }}</span>
                </label>
            @endforeach
        </fieldset>

        <div class="field">
            <label class="cm-visually-hidden" for="body">What's on your mind?</label>
            <textarea class="input cm-textarea" id="body" name="body" rows="8" maxlength="2000"
                      placeholder="What's on your mind?" required>{{ old('body') }}</textarea>
            @error('body')
                <p class="field__error">{{ $message }}</p>
            @enderror
            @error('category')
                <p class="field__error">{{ $message }}</p>
            @enderror
        </div>

        <div class="card cm-anon">
            <input type="hidden" name="anonymous" value="0">
            <label class="cm-anon__row" for="anonymous">
                <strong>Post anonymously</strong>
                <span class="cm-switch">
                    <input type="checkbox" id="anonymous" name="anonymous" value="1" checked>
                    <span class="cm-switch__track" aria-hidden="true"></span>
                </span>
            </label>
            <p class="text-small text-muted" id="anon-hint">You'll appear under a random alias (like {{ $alias }}) in this thread</p>
        </div>

        <div class="field">
            <label class="cm-photo" for="photo">
                @include('components.icon', ['name' => 'clip'])
                <span id="photo-name">Add photo</span>
            </label>
            <input class="cm-visually-hidden" type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
            <p class="text-small text-muted">JPG, PNG or WebP, up to 4 MB. Location data is removed from the photo.</p>
            @error('photo')
                <p class="field__error">{{ $message }}</p>
            @enderror
        </div>

        <p class="text-small text-muted">Need someone to talk to? Guidance support is available. <a class="cm-underline" href="{{ route('page.help') }}">See help</a></p>
    </form>
@endsection
