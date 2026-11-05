@extends('layouts.community')

@section('title', 'Points history · Kanlungan')
@section('nav', 'me')

@section('content')
    <div class="stack">
        <header class="cm-header"><a href="{{ route('community.me') }}" class="cm-back">@include('components.icon', ['name' => 'back']) Points history</a></header>
        @forelse ($history as $group)
            <section class="cm-ledger" aria-label="{{ $group['title'] }}">
                <h2 class="cm-ledger__title">{{ $group['title'] }}</h2>
                <ul class="cm-ledger__list">
                    @foreach ($group['rows'] as $row)
                        @include('components.point-history-row', ['row' => $row])
                    @endforeach
                </ul>
            </section>
        @empty
            <div class="card cm-empty"><p class="text-muted">No points yet.</p></div>
        @endforelse
    </div>
@endsection
