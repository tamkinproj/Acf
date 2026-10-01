@extends('install.layout')
@section('title','System')
@section('content')
<h1>Platform configuration</h1>
<p class="lead">Basic settings for the platform. Each foundation you create later has its own settings, including currency.</p>
<form method="post" action="{{ url('/install/system') }}">
  @csrf
  <label for="app_name">Platform name</label>
  <input id="app_name" name="app_name" type="text" required value="{{ old('app_name', $v['app_name']) }}">
  <label for="app_url">Application URL</label>
  <input id="app_url" name="app_url" type="url" required value="{{ old('app_url', $v['app_url']) }}">
  <div class="row">
    <div><label for="timezone">Timezone</label>
      <select id="timezone" name="timezone">@foreach($timezones as $tz)<option value="{{ $tz }}" @selected(old('timezone', $v['timezone']) === $tz)>{{ $tz }}</option>@endforeach</select></div>
    <div><label for="locale">Default language</label>
      <select id="locale" name="locale">@foreach(['en'=>'English','fil'=>'Filipino','ar'=>'العربية'] as $code => $label)@if(in_array($code,$locales,true))<option value="{{ $code }}" @selected(old('locale', $v['locale']) === $code)>{{ $label }}</option>@endif @endforeach</select></div>
  </div>
  <div class="actions"><a class="btn secondary" href="{{ url('/install/database') }}">Back</a><button class="btn" type="submit">Continue</button></div>
</form>
@endsection
