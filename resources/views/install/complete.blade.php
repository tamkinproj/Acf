@extends('install.layout')
@section('title','Installation complete')
@section('content')
<div class="center">
  <div class="big" aria-hidden="true">✓</div>
  <h1>Installation Complete</h1>
  <p class="lead">The platform is ready. Sign in as the Platform Admin to create your first foundation.</p>
</div>
<dl class="kv">
  <dt>Platform</dt><dd>{{ $summary['platform'] }}</dd>
  <dt>Platform Admin</dt><dd>{{ $summary['administrator'] }}</dd>
  <dt>System version</dt><dd>{{ $summary['version'] }}</dd>
</dl>
<div class="actions"><span></span><a class="btn" href="{{ url('/') }}">Sign in</a></div>
@endsection
