@extends('layouts.community')

@section('title', 'Alerts · Kanlungan')
@section('nav', 'alerts')

@section('content')
    <div class="stack">
        <header class="cm-header">
            <h1>Alerts</h1>
            @if ($alerts->contains('unread', true))
                <form method="POST" action="{{ route('alerts.read-all') }}">
                    @csrf
                    <button type="submit" class="link-btn">Mark all read</button>
                </form>
            @endif
        </header>

        @forelse ($alerts as $alert)
            @include('components.alert-item', ['alert' => $alert])
        @empty
            <div class="card cm-empty"><p class="text-muted">No alerts yet.</p></div>
        @endforelse
    </div>
@endsection
