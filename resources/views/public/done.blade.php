@extends('public.layout')
@section('title','Registration received')
@section('content')
<div class="center">
  <div class="big" aria-hidden="true">✓</div>
  <h1>Thank you</h1>
  <p class="lead">{{ $message ?: 'Your registration was received. The foundation will review it and contact you if anything more is needed.' }}</p>
</div>
<dl class="kv">
  <dt>Reference</dt><dd><code>{{ $info['reference'] }}</code></dd>
  <dt>Your private link</dt><dd><a href="{{ $info['status_url'] }}">{{ $info['status_url'] }}</a></dd>
</dl>
<div class="alert warn"><strong>Keep this page.</strong> Save or photograph the reference and the private link now — this is the only time they are shown. You will need the link to see the result or to fix anything the foundation asks you to correct. Do not share it.</div>
@endsection
