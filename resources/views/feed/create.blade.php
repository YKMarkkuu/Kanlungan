@extends('layouts.community')

@section('title', 'New post · Kanlungan')
@section('hide-nav', '1')
@section('main-class', 'cm-main--compose')

@section('content')
    <form method="POST" action="{{ route('post.store') }}" class="cm-compose-form" id="post-form" enctype="multipart/form-data"
          data-alias="{{ $alias }}" data-display-name="{{ trim((string) auth()->user()->display_name) !== '' ? auth()->user()->display_name : $alias }}" data-has-name="{{ $hasDisplayName ? '1' : '0' }}">
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

        <div class="cm-collapse" id="market-fields" data-market-fields aria-hidden="true">
            <div class="cm-collapse__inner">
                <div class="cm-market">
                    <div class="cm-items-editor {{ count($itemRows) > 1 ? 'is-multi' : '' }}" id="items-editor" data-max-items="{{ $maxItems }}">
                        <span class="field__label" id="items-title">{{ count($itemRows) > 1 ? 'Items for sale' : 'Price' }}</span>

                        <div class="cm-item-rows" id="item-rows">
                            @foreach ($itemRows as $i => $row)
                                @include('components.item-row', ['i' => $i, 'label' => $row['label'] ?? '', 'price' => $row['price'] ?? ''])
                            @endforeach
                        </div>

                        @if ($errors->has('items') || $errors->has('items.*'))
                            <p class="field__error">{{ $errors->first('items') ?: $errors->first('items.*') }}</p>
                        @endif

                        <button type="button" class="cm-add-item" id="add-item-btn" @if (count($itemRows) >= $maxItems) hidden @endif>+ Add another item</button>
                    </div>

                    <div class="field">
                        <label class="field__label" for="condition">Condition <span class="text-muted">(optional)</span></label>
                        <select class="input" id="condition" name="condition" disabled>
                            <option value="">Not specified</option>
                            @foreach ($conditions as $condition)
                                <option value="{{ $condition }}" @selected(old('condition') === $condition)>{{ $condition }}</option>
                            @endforeach
                        </select>
                        @error('condition') <p class="field__error">{{ $message }}</p> @enderror
                    </div>

                    <p class="text-small text-muted" id="market-name-hint" @if ($hasDisplayName) hidden @endif>
                        Listings show your display name. Add one in <a class="cm-underline" href="{{ route('community.me') }}">Me</a> before posting.
                    </p>
                </div>
            </div>
        </div>

        <template id="item-row-template">@include('components.item-row', ['i' => '__i__'])</template>

        <div class="cm-compose cm-compose--canvas cm-animate-in" style="--cm-delay: 50ms">
            <div class="cm-compose__row" data-compose-avatar-row>
                <x-avatar :avatar-path="auth()->user()->avatar_path" :name="auth()->user()->display_name" class="cm-compose__avatar cm-compose__avatar--user" data-compose-avatar-user />
                <span class="cm-avatar cm-compose__avatar cm-compose__avatar--anonymous" data-compose-avatar-anonymous aria-hidden="true">
                    @include('components.icon', ['name' => 'mask'])
                </span>

                <div class="cm-compose__main">
                    <p class="text-small text-muted cm-compose__as" id="anon-hint"></p>

                    <label class="cm-visually-hidden" for="body">What's on your mind?</label>
                    <textarea class="cm-compose__input" id="body" name="body" rows="5" maxlength="2000"
                              placeholder="What's on your mind?" required>{{ old('body') }}</textarea>

                    @error('body')
                        <p class="field__error">{{ $message }}</p>
                    @enderror
                    @error('category')
                        <p class="field__error">{{ $message }}</p>
                    @enderror
                    @error('display_name')
                        <p class="field__error">{{ $message }} <a class="cm-underline" href="{{ route('community.me') }}">Go to Me</a></p>
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
