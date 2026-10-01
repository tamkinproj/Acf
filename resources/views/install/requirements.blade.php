@extends('install.layout')
@section('title','Requirements')
@section('content')
<h1>System requirements</h1>
<p class="lead">Checking that this server can run the Foundation system.</p>
<ul class="checks">
@foreach($checks as $c)
  <li>
    <span class="ic {{ $c['ok'] ? 'ok' : ($c['required'] ? 'no' : 'opt') }}" aria-hidden="true">{{ $c['ok'] ? '✓' : ($c['required'] ? '✕' : '–') }}</span>
    <div>
      {{ $c['label'] }}@unless($c['required']) <span class="hint">(optional)</span>@endunless
      @unless($c['ok'])<small>{{ $c['detail'] }}</small>@endunless
    </div>
  </li>
@endforeach
</ul>
<form method="post" action="{{ url('/install/requirements') }}">
  @csrf
  <div class="actions">
    <a class="btn ghost" href="{{ url('/install/requirements') }}">Re-check</a>
    <button class="btn" type="submit" @disabled(! $passes)>Continue</button>
  </div>
</form>
@endsection
