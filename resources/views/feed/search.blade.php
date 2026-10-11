@extends('layouts.community')

@section('title', 'Search · Kanlungan')
@section('nav', 'search')

@section('content')
    <div class="stack">
        <header class="cm-header"><h1>Search</h1></header>

        <form method="GET" action="{{ route('search') }}" class="cm-search" role="search">
            <input class="input" type="search" name="q" value="{{ $q }}" placeholder="Search posts" maxlength="80" autofocus>
            <button type="submit" class="btn btn--primary cm-send">Search</button>
        </form>

        @if ($q === '')
            <div class="card cm-empty"><p class="text-muted">Type at least 2 letters to search posts.</p></div>
        @else
            @forelse ($results as $post)
                @include('components.post-card', ['post' => $post])
            @empty
                <div class="card cm-empty"><p><strong>No posts found.</strong></p><p class="text-small text-muted">Try a different word.</p></div>
            @endforelse
        @endif
    </div>
@endsection
