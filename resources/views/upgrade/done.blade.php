@extends('install.layout')
@section('title','Update complete')
@section('content')
<div class="center">
  <div class="big" aria-hidden="true">✓</div>
  <h1>Update complete</h1>
  <p class="lead">Your system is now on version {{ $result['to'] }}.</p>
</div>
<dl class="kv">
  <dt>Role changes</dt><dd>{{ $result['roles_migrated'] }}</dd>
  <dt>New permissions</dt><dd>{{ $result['permissions_added'] }}</dd>
  @if($createdAdmin)<dt>Platform Admin</dt><dd>Created. Sign in with it to manage foundations.</dd>@endif
</dl>
<div class="actions"><span></span><a class="btn" href="{{ url('/') }}">Open the system</a></div>
@endsection
