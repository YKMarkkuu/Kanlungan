@extends('layouts.community')

@section('title', 'Saved posts · Kanlungan')
@section('nav', 'me')

@section('content')
    <div class="stack">
        <header class="cm-header"><a href="{{ route('community.me') }}" class="cm-back">@include('components.icon', ['name' => 'back']) Saved</a></header>
        <p class="text-small text-muted">Only you can see your saved posts.</p>
        <div class="cm-saved-list" data-saved-list>
            @foreach ($posts as $post)
                <div class="cm-saved-item" data-saved-item="{{ $post['id'] }}">
                    <div class="cm-saved-item__inner">
                        @include('components.post-card', ['post' => $post])
                    </div>
                </div>
            @endforeach
        </div>
        <div data-saved-empty @if (count($posts) > 0) hidden @endif>
            <x-empty-state icon="bookmark" title="Nothing saved yet" message="Tap the ribbon on a post to keep it here." />
        </div>
    </div>
@endsection
