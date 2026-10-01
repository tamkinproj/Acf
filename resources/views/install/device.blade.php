@extends('install.layout')
@section('title','Device')
@section('content')
<h1>Register this installation</h1>
<p class="lead">Each installation is a device with its own permanent identifier. Give it a friendly name — you can rename it later.</p>
<form method="post" action="{{ url('/install/device') }}">
  @csrf
  <label for="name">Device name</label>
  <input id="name" name="name" type="text" required value="{{ old('name', $v['name']) }}">
  <label for="type">Device type</label>
  <select id="type" name="type">@foreach($types as $t)<option value="{{ $t }}" @selected(old('type', $v['type']) === $t)>{{ ucfirst($t) }}</option>@endforeach</select>
  <div class="actions"><a class="btn ghost" href="{{ url('/install/admin') }}">Back</a><button class="btn" type="submit">Continue</button></div>
</form>
@endsection
