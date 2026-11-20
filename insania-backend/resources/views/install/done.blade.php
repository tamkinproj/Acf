@extends('install.layout')
@section('title','Installed')
@section('body')
@if($already)
<h1>Already installed</h1><p>Nothing to do here.</p>
@elseif(!empty($reconnected))
<h1>Reconnected ✓</h1>
<div class="card"><p>The system was already installed. Its settings were restored and <strong>no data was changed</strong>. Sign in with your existing account.</p>
<p>The API is at <code>{{ $api }}</code>.</p><p>The setup code was deleted and this page is now switched off.</p></div>
@else
<h1>Installed ✓</h1>
<div class="card"><p>The system is ready. Sign in with <strong>{{ $email }}</strong>.</p><p>The API is at <code>{{ $api }}</code>.</p>
<p>The setup code was deleted and this page is now switched off.</p></div>
@endif
@endsection
