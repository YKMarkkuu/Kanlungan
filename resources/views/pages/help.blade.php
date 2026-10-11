@extends('layouts.community')

@section('title', 'Help · Kanlungan')
@section('nav', 'me')

@section('content')
    <div class="stack">
        <header class="cm-header"><a href="{{ route('community.me') }}" class="cm-back">@include('components.icon', ['name' => 'back']) Help</a></header>

        <div class="card stack">
            <h2>Need someone to talk to?</h2>
            <p>You do not have to handle this alone. You can visit the Guidance Office at your campus, or call a free, confidential hotline any time:</p>
            <ul class="rules-list">
                <li><strong>NCMH Crisis Hotline</strong>: 1553 (landline), 0917 899 8727 or 0966 351 4518. Open 24/7.</li>
                <li><strong>Emergency</strong>: 911</li>
            </ul>
            <p class="text-small text-muted">Kanlungan is not emergency help. If you or someone else is in immediate danger, call 911 or go to the nearest hospital.</p>
        </div>

        <div class="card stack">
            <h2>Worried about someone's post?</h2>
            <p class="text-small">Tap the flag on the post and choose <strong>Self-harm</strong>. It goes to the moderators right away.</p>
        </div>
    </div>
@endsection
