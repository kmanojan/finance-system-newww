@extends('layouts.share')
@section('title', 'Link Unavailable')

@section('content')
<div class="card" style="text-align: center; padding: 4rem 2rem; max-width: 500px; margin: 8vh auto; border-radius: 16px;">
    <div style="width:80px; height:80px; background:#fee2e2; color:#dc2626; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; margin-bottom:1.25rem;">
        <ion-icon name="alert-circle-outline" style="font-size: 2.8rem;"></ion-icon>
    </div>
    <h1 style="color: var(--text-heading); font-weight:800; margin-bottom: 0.5rem; font-size:1.8rem;">Link Expired or Revoked</h1>
    <p class="text-muted" style="margin-bottom: 1.5rem; font-size:0.95rem; line-height:1.6;">This shared portal link is invalid, has reached its expiration date, or access was revoked by the issuer.</p>
</div>
@endsection
