@extends('layouts.community')

@section('title', 'Account suspended · Kanlungan')
@section('hide-nav', '1')

@section('content')
    <div class="stack">
        <div class="card stack">
            <h1>Your account is suspended</h1>
            <p>A moderator suspended your access to the community. If you think this is a mistake, please contact the Guidance Office.</p>
            <form method="POST" action="{{ route('auth.logout') }}">
                @csrf
                <button type="submit" class="btn btn--block">Log out</button>
            </form>
        </div>
    </div>
@endsection
