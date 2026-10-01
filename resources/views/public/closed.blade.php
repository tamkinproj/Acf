@extends('public.layout')
@section('title','Registration closed')
@section('content')
<h1>Registration is not open</h1>
<p class="lead">This registration form is not accepting submissions right now. If you were expecting it to be open, please contact the foundation.</p>
@if($foundation->phone || $foundation->email)
  <dl class="kv">
    @if($foundation->phone)<dt>Phone</dt><dd>{{ $foundation->phone }}</dd>@endif
    @if($foundation->email)<dt>Email</dt><dd>{{ $foundation->email }}</dd>@endif
  </dl>
@endif
@endsection
