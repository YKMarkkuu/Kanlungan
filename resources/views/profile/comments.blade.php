@extends('layouts.community')

@section('title', 'My comments · Kanlungan')
@section('nav', 'me')

@section('content')
    <div class="stack">
        <header class="cm-header"><a href="{{ route('community.me') }}" class="cm-back">@include('components.icon', ['name' => 'back']) My comments</a></header>
        @forelse ($comments as $comment)
            <a class="card cm-alert" href="{{ route('post.show', $comment['post_id']) }}#comment-{{ $comment['id'] }}">
                <span>{{ $comment['body'] }}</span>
                @if ($comment['post_excerpt'])
                    <span class="text-small text-muted">On: {{ $comment['post_excerpt'] }}</span>
                @endif
                <span class="text-small text-muted">{{ $comment['is_anonymous'] ? 'As '.$comment['author_alias'] : 'With your name' }} · {{ $comment['time_ago'] }}</span>
            </a>
        @empty
            <div class="card cm-empty"><p class="text-muted">You haven't commented yet.</p></div>
        @endforelse
    </div>
@endsection
