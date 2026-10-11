@extends('layouts.community')

@section('title', 'Notice · Kanlungan')
@section('nav', 'alerts')

@section('content')
    <div class="stack">
        <header class="cm-header">
            <a href="{{ route('alerts') }}" class="cm-back">@include('components.icon', ['name' => 'back']) Alerts</a>
        </header>

        <div class="card stack">
            <h1>{{ $alert->title }}</h1>
            <p>{{ $alert->body }}</p>
            <p class="text-small text-muted">{{ $alert->created_at?->diffForHumans() }}</p>
            <p class="text-small text-muted">Questions about this notice? Read the <a class="cm-underline" href="{{ route('page.rules') }}">community rules</a>.</p>
        </div>
    </div>
@endsection
