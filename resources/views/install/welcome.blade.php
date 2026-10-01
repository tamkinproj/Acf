@extends('install.layout')
@section('title','Welcome')
@section('content')
<h1>Welcome</h1>
<p class="lead">Let's set up your foundation system. This takes a few minutes and works without an internet connection.</p>
@if($error)
  <div class="alert warn" role="alert">
    <strong>A previous installation attempt did not finish.</strong><br>
    Step “{{ $error['step'] ?? 'unknown' }}”: {{ $error['message'] ?? '' }}<br>
    Nothing was deleted. Review your answers and run the installation again.
  </div>
@endif
<div class="actions"><span></span><a class="btn" href="{{ url('/install/'.$step) }}">{{ $error ? 'Resume Setup' : 'Start Setup' }}</a></div>
@endsection
