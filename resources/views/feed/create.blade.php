@extends('layouts.community')

@section('title', 'New post · Kanlungan')
@section('hide-nav', '1')
@section('main-class', 'cm-main--compose')

@section('content')
    <form method="POST" action="{{ route('post.store') }}" class="cm-compose-form" id="post-form" enctype="multipart/form-data" data-alias="{{ $alias }}">
        @csrf

        <header class="cm-header cm-animate-fade">
            <a href="{{ route('feed') }}" class="cm-icon-btn" aria-label="Cancel">@include('components.icon', ['name' => 'close'])</a>
            <h1 class="cm-header__title">New post</h1>
            <span class="cm-icon-btn cm-compose__spacer" aria-hidden="true"></span>
        </header>

        <div class="cm-chips cm-chips--compose cm-animate-fade" role="radiogroup" aria-label="Category" style="--cm-delay: 30ms">
            @foreach ($categories as $category)
                <label>
                    <input type="radio" name="category" value="{{ $category }}" class="cm-visually-hidden"
                        {{ old('category', 'Share') === $category ? 'checked' : '' }}>
                    <span class="chip">{{ $category }}</span>
                </label>
            @endforeach
        </div>

        <div class="cm-compose cm-compose--canvas cm-animate-in" style="--cm-delay: 50ms">
            <div class="cm-compose__row">
                <x-avatar :avatar-path="auth()->user()->avatar_path" :name="auth()->user()->display_name" class="cm-compose__avatar" />

                <div class="cm-compose__main">
                    <p class="text-small text-muted cm-compose__as" id="anon-hint">You'll appear under a random alias (like {{ $alias }}) in this thread</p>

                    <label class="cm-visually-hidden" for="body">What's on your mind?</label>
                    <textarea class="cm-compose__input" id="body" name="body" rows="5" maxlength="2000"
                              placeholder="What's on your mind?" required>{{ old('body') }}</textarea>

                    @error('body')
                        <p class="field__error">{{ $message }}</p>
                    @enderror
                    @error('category')
                        <p class="field__error">{{ $message }}</p>
                    @enderror

                    <div id="image-preview-container" class="cm-compose__preview hidden"></div>
                </div>
            </div>

            {{-- Filled by community.js on 422 / network errors. --}}
            <p class="field__error cm-compose__error" id="post-error" role="alert" aria-live="polite" hidden></p>
        </div>

        @if ($errors->has('photos') || $errors->has('photos.*'))
            <p class="field__error">{{ $errors->first('photos') ?: $errors->first('photos.*') }}</p>
        @endif
        <p class="text-small text-muted">Up to 3 photos (JPG, PNG or WebP). Photos are resized automatically and location data is removed.</p>
        <p class="text-small text-muted">Need someone to talk to? Guidance support is available. <a class="cm-underline" href="{{ route('page.help') }}">See help</a></p>

        <div class="cm-compose__bar cm-animate-in" style="--cm-delay: 100ms">
            <div class="cm-compose__tools">
                <label class="cm-icon-btn cm-compose__attach" for="photo" title="Add photos (max 3)">
                    @include('components.icon', ['name' => 'clip'])
                    <span class="cm-visually-hidden" id="photo-name">Add photos (max 3)</span>
                </label>
                <input class="cm-visually-hidden" type="file" id="photo" name="photos[]" multiple accept="image/jpeg,image/png,image/webp">

                <input type="hidden" name="anonymous" value="0">
                <label class="cm-anon-badge" for="anonymous">
                    <input class="cm-visually-hidden" type="checkbox" id="anonymous" name="anonymous" value="1" checked>
                    <span class="cm-anon-badge__pill">
                        @include('components.icon', ['name' => 'mask'])
                        <span>Anonymous</span>
                    </span>
                </label>
            </div>

            <span id="char-count" class="cm-compose__count text-small text-muted">0 / 2000</span>
            <button type="submit" class="btn btn--primary cm-compose__post" id="post-btn" disabled>Post</button>
        </div>
    </form>
@endsection
