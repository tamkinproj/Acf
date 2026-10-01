@extends('install.layout')
@section('title','Review')
@section('content')
<h1>Review and install</h1>
<p class="lead">Check these details. Installing creates the database structure and the Platform Admin account. Foundations are created afterwards, from the platform.</p>
@if($error)
  <div class="alert warn" role="alert"><strong>The last attempt stopped at “{{ $error['step'] ?? 'unknown' }}”:</strong> {{ $error['message'] ?? '' }}<br>Nothing was deleted. You can run the installation again.</div>
@endif
<dl class="kv">
  <dt>Database</dt><dd>{{ ($d['database']['driver'] ?? '') === 'sqlite' ? 'SQLite ('.($d['database']['sqlite_name'] ?? '').')' : ($d['database']['host'] ?? '').':'.($d['database']['port'] ?? '').' / '.($d['database']['database'] ?? '') }}</dd>
  <dt>Application</dt><dd>{{ $d['system']['app_name'] ?? '' }} — {{ $d['system']['app_url'] ?? '' }}</dd>
  <dt>Timezone / language</dt><dd>{{ $d['system']['timezone'] ?? '' }} · {{ $d['system']['locale'] ?? '' }}</dd>
  <dt>Platform Admin</dt><dd>{{ $d['admin']['name'] ?? '' }} ({{ $d['admin']['email'] ?? '' }})</dd>
</dl>
<p class="hint">Passwords are not shown.</p>
<form method="post" action="{{ url('/install/run') }}">
  @csrf
  <div class="actions"><a class="btn secondary" href="{{ url('/install/admin') }}">Back</a><button class="btn" type="submit">Install Now</button></div>
</form>
@endsection
