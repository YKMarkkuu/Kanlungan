@extends('layouts.guest')

@section('title', 'DPO identity reveal · Kanlungan')

@section('head')
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="{{ asset('css/staff.css') }}">
@endsection

@section('content')
    <div class="stack stack--lg sf-login">
        <header class="auth-header">
            <h1>DPO identity reveal</h1>
            <p class="text-muted">Enter a report ID and a reason. Every reveal is recorded in the audit log.</p>
        </header>

        @if ($errors->any())
            <div class="sf-notice sf-notice--error" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        @if (session('revealed_identity'))
            <div class="sf-notice" role="status" aria-live="polite">
                <p>Identity for report #{{ session('revealed_identity.report_id') }}:</p>
                <strong>{{ session('revealed_identity.email') }}</strong>
            </div>
        @endif

        <form class="stack" method="POST" action="{{ route('staff.dpo-reveal.submit') }}">
            @csrf
            <div class="field">
                <label class="field__label" for="report_id">Report ID</label>
                <input class="input" id="report_id" name="report_id" type="number" min="1" value="{{ old('report_id') }}" required>
            </div>
            <div class="field">
                <label class="field__label" for="reason">Reason for reveal</label>
                <input class="input" id="reason" name="reason" type="text" minlength="3" maxlength="500" value="{{ old('reason') }}" required>
            </div>
            <button type="submit" class="btn btn--primary btn--block">Reveal identity</button>
        </form>

        <form method="POST" action="{{ route('auth.logout') }}">
            @csrf
            <button type="submit" class="link-btn">Sign out</button>
        </form>
    </div>
@endsection
