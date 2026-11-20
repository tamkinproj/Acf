@extends('install.layout')
@section('title','Installed')
@section('body')
@if($already)
<h1>Already installed</h1><p>Nothing to do here.</p>
@elseif(!empty($reconnected))
<h1>Reconnected ✓</h1>
<div class="card"><p>The system was already installed. Its settings were restored and <strong>no data was changed</strong>. Sign in with your existing account.</p>
<p>The API is at <code>{{ $api }}</code>.</p><p>This page is now switched off for good: it answers "not found" to everyone.</p><p>To update later, open <code>/upgrade?code=…</code> (or use the app: System Admin, Updates). The code is the text inside <code>storage/app/install/token</code>; it was replaced with a new one.</p></div>
@else
<h1>Installed ✓</h1>
<div class="card"><p>The system is ready. Sign in with <strong>{{ $email }}</strong>.</p><p>The API is at <code>{{ $api }}</code>.</p>
<p>This page is now switched off for good: it answers "not found" to everyone.</p><p>To update later, open <code>/upgrade?code=…</code> (or use the app: System Admin, Updates). The code is the text inside <code>storage/app/install/token</code>; it was replaced with a new one.</p></div>
@endif
@endsection
