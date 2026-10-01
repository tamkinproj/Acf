@extends('install.layout')
@section('title','Installation complete')
@section('content')
<div class="center">
  <div class="big" aria-hidden="true">✓</div>
  <h1>Installation Complete</h1>
  <p class="lead">Foundation Management System is ready to use.</p>
</div>
<dl class="kv">
  <dt>Foundation</dt><dd>{{ $summary['foundation'] }}</dd>
  <dt>Administrator</dt><dd>{{ $summary['administrator'] }}</dd>
  <dt>Device</dt><dd>{{ $summary['device'] }}</dd>
  <dt>System version</dt><dd>{{ $summary['version'] }}</dd>
</dl>
<div class="actions"><span></span><a class="btn" href="{{ url('/') }}">Go to Dashboard</a></div>
@endsection
