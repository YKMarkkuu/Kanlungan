@extends('layouts.community')

@section('title', 'My posts · Kanlungan')
@section('nav', 'me')

@section('content')
    <div class="stack">
        <header class="cm-header"><a href="{{ route('community.me') }}" class="cm-back">@include('components.icon', ['name' => 'back']) My posts</a></header>
        @forelse ($posts as $post)
            @include('components.post-card', ['post' => $post])
        @empty
            <x-empty-state icon="home" title="No posts yet" message="Your posts will appear here." />
        @endforelse
    </div>
@endsection
