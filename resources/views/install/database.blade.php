@extends('install.layout')
@section('title','Database')
@section('content')
<h1>Database</h1>
<p class="lead">The Foundation system uses its own database, separate from every other application. Create an empty database first.</p>
@if($testResult)
  <div class="alert {{ $testResult['ok'] ? 'good' : 'bad' }}" role="status">{{ $testResult['message'] }}</div>
@endif
<form method="post" action="{{ url('/install/database') }}" autocomplete="off">
  @csrf
  <div class="tabs" role="radiogroup" aria-label="Database type">
    @if($mysql)<label><input type="radio" name="driver" value="mysql" @checked(old('driver', $db['driver']) === 'mysql')> MySQL / MariaDB</label>@endif
    @if($sqlite)<label><input type="radio" name="driver" value="sqlite" @checked(old('driver', $db['driver']) === 'sqlite')> SQLite (single device)</label>@endif
  </div>
  <div class="row">
    <div><label for="host">Database host</label><input id="host" name="host" type="text" value="{{ old('host', $db['host'] ?? '127.0.0.1') }}"></div>
    <div><label for="port">Database port</label><input id="port" name="port" type="number" min="1" max="65535" value="{{ old('port', $db['port'] ?? 3306) }}"></div>
  </div>
  <label for="database">Database name</label>
  <input id="database" name="database" type="text" value="{{ old('database', $db['database'] ?? 'foundation') }}">
  <div class="row">
    <div><label for="username">Username</label><input id="username" name="username" type="text" value="{{ old('username', $db['username'] ?? '') }}" autocomplete="off"></div>
    <div><label for="db_password">Password</label><input id="db_password" name="db_password" type="password" autocomplete="new-password"><span class="hint">Never shown again after you continue.</span></div>
  </div>
  <label for="sqlite_name">SQLite file name <span class="hint">(SQLite only; stored privately under storage/app/db)</span></label>
  <input id="sqlite_name" name="sqlite_name" type="text" value="{{ old('sqlite_name', $db['sqlite_name'] ?? 'foundation') }}">
  <div class="actions">
    <button class="btn ghost" type="submit" name="action" value="test">Test Database Connection</button>
    <button class="btn" type="submit" name="action" value="save">Continue</button>
  </div>
</form>
@endsection
