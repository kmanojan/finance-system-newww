@extends('layouts.app')
@section('title','Search')
@section('content')
<header class="page-header"><div><h1>Search finance</h1><p class="subtitle">Find projects, invoices, parties and transactions.</p></div></header>
<form method="GET" class="toolbar"><input class="form-control" type="search" name="q" value="{{ $q }}" placeholder="Name or reference (at least 2 characters)" aria-label="Search finance" style="max-width:600px"><button class="btn btn-primary">Search</button></form>
<div class="ds-panel">@forelse($results as $result)<a class="attention-row" href="{{ $result['url'] }}"><div><strong>{{ $result['name'] }}</strong><span>{{ $result['type'] }}</span></div><span>{{ $result['detail'] }}</span></a>@empty<p class="text-muted">{{ $q ? 'No matching records.' : 'Enter a name or reference to search.' }}</p>@endforelse</div>
@endsection
