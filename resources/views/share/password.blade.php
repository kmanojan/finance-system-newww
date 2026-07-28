@extends('layouts.share')
@section('title', 'Password Protected Link')

@section('content')
<div class="card" style="max-width: 440px; margin: 8vh auto; padding: 2.5rem; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08);">
    <div style="text-align: center; margin-bottom: 2rem;">
        <div style="width:70px; height:70px; background:var(--primary-light); color:var(--primary); border-radius:50%; display:inline-flex; align-items:center; justify-content:center; margin-bottom:1rem;">
            <ion-icon name="lock-closed-outline" style="font-size: 2.2rem;"></ion-icon>
        </div>
        <h2 style="color: var(--text-heading); font-weight:800; margin:0 0 0.5rem 0; font-size:1.6rem;">Protected Document</h2>
        <p class="text-muted" style="font-size:0.9rem; margin:0;">This secure link is password protected. Enter the password provided to unlock.</p>
    </div>

    <form action="/share/{{ $token }}/password" method="POST">
        @csrf
        <div style="margin-bottom: 1.5rem;">
            <label style="font-size:0.8rem; font-weight:600; color:var(--text-muted); display:block; margin-bottom:0.4rem;">Password</label>
            <input type="password" name="password" class="form-control" style="width: 100%; padding: 0.8rem 1rem; font-size: 1rem; border-radius:10px;" placeholder="Enter access password" required autofocus>
            @error('password')
                <div style="color: var(--danger); font-size: 0.85rem; margin-top: 0.5rem; font-weight:600;">{{ $message }}</div>
            @enderror
        </div>
        <button type="submit" class="btn btn-primary-gradient" style="width: 100%; padding: 0.85rem; font-weight:700; border-radius:10px; font-size:1rem; cursor:pointer;">
            <ion-icon name="key-outline" style="vertical-align:middle;"></ion-icon> Access Portal
        </button>
    </form>
</div>
@endsection
