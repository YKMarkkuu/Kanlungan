@extends('layouts.community')

@section('title', 'My posts · Kanlungan')
@section('nav', 'me')

@section('content')
    <div class="stack">
        <header class="cm-header"><a href="{{ route('community.me') }}" class="cm-back">@include('components.icon', ['name' => 'back']) My posts</a></header>
        @forelse ($posts as $post)
            @include('components.post-card', ['post' => $post])
        @empty
            <div class="card cm-empty"><p class="text-muted">You haven't posted yet.</p></div>
        @endforelse
    </div>
@endsection
