@extends('install.layout')
@section('title','System')
@section('content')
<h1>System configuration</h1>
<p class="lead">Basic settings. You can change most of these later in Settings.</p>
<form method="post" action="/install/system">
  @csrf
  <label for="app_name">Application name</label>
  <input id="app_name" name="app_name" type="text" required value="{{ old('app_name', $v['app_name']) }}">
  <label for="app_url">Application URL</label>
  <input id="app_url" name="app_url" type="url" required value="{{ old('app_url', $v['app_url']) }}">
  <div class="row">
    <div><label for="timezone">Timezone</label>
      <select id="timezone" name="timezone">@foreach($timezones as $tz)<option value="{{ $tz }}" @selected(old('timezone', $v['timezone']) === $tz)>{{ $tz }}</option>@endforeach</select></div>
    <div><label for="locale">Default language</label>
      <select id="locale" name="locale">@foreach(['en'=>'English','fil'=>'Filipino','ar'=>'العربية'] as $code => $label)@if(in_array($code,$locales,true))<option value="{{ $code }}" @selected(old('locale', $v['locale']) === $code)>{{ $label }}</option>@endif @endforeach</select></div>
  </div>
  <div class="row">
    <div><label for="currency">Currency</label>
      <select id="currency" name="currency">@foreach($currencies as $c)<option value="{{ $c }}" @selected(old('currency', $v['currency']) === $c)>{{ $c }}</option>@endforeach</select></div>
    <div><label for="deployment_model">Deployment</label>
      <select id="deployment_model" name="deployment_model">
        @foreach(['central'=>'Central server','local_server'=>'Local foundation server','standalone'=>'Standalone (one device)'] as $k => $label)@if(in_array($k,$models,true))<option value="{{ $k }}" @selected(old('deployment_model', $v['deployment_model']) === $k)>{{ $label }}</option>@endif @endforeach
      </select></div>
  </div>
  <div class="actions"><a class="btn ghost" href="/install/database">Back</a><button class="btn" type="submit">Continue</button></div>
</form>
@endsection
