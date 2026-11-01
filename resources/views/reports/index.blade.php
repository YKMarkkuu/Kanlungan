@extends('layouts.community')

@section('title', 'Community Verdicts · Kanlungan')
@section('nav', 'alerts')

@section('content')
    <div class="stack">
        <header class="cm-header"><h1>Community Verdicts</h1></header>

        <div class="card cm-notice" role="note">
            Help keep Kanlungan safe. Review these reports and cast your vote.
        </div>

        @include('reports._list', ['reports' => $reports])
    </div>
@endsection
