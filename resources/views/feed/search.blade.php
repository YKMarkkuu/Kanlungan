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
            <x-empty-state icon="search" title="Search Kanlungan" message="Type at least 2 letters to search posts." />
        @else
            @forelse ($results as $post)
                @include('components.post-card', ['post' => $post])
            @empty
                <x-empty-state icon="search" title="No posts found" message="Try a different word." />
            @endforelse
        @endif
    </div>
@endsection
