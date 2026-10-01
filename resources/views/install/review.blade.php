@extends('install.layout')
@section('title','Review')
@section('content')
<h1>Review and install</h1>
<p class="lead">Check these details. Installing creates the database structure and your administrator account.</p>
@if($error)
  <div class="alert warn" role="alert"><strong>The last attempt stopped at “{{ $error['step'] ?? 'unknown' }}”:</strong> {{ $error['message'] ?? '' }}<br>Nothing was deleted. You can run the installation again.</div>
@endif
<dl class="kv">
  <dt>Database</dt><dd>{{ ($d['database']['driver'] ?? '') === 'sqlite' ? 'SQLite ('.($d['database']['sqlite_name'] ?? '').')' : ($d['database']['host'] ?? '').':'.($d['database']['port'] ?? '').' / '.($d['database']['database'] ?? '') }}</dd>
  <dt>Application</dt><dd>{{ $d['system']['app_name'] ?? '' }} — {{ $d['system']['app_url'] ?? '' }}</dd>
  <dt>Timezone / language</dt><dd>{{ $d['system']['timezone'] ?? '' }} · {{ $d['system']['locale'] ?? '' }} · {{ $d['system']['currency'] ?? '' }}</dd>
  <dt>Deployment</dt><dd>{{ str_replace('_',' ', $d['system']['deployment_model'] ?? '') }}</dd>
  <dt>Foundation</dt><dd>{{ $d['foundation']['name'] ?? '' }}</dd>
  <dt>Administrator</dt><dd>{{ $d['admin']['name'] ?? '' }} ({{ $d['admin']['email'] ?? '' }})</dd>
  <dt>Device</dt><dd>{{ $d['device']['name'] ?? '' }} ({{ $d['device']['type'] ?? '' }})</dd>
</dl>
<p class="hint">Passwords are not shown.</p>
<form method="post" action="{{ url('/install/run') }}">
  @csrf
  <div class="actions"><a class="btn ghost" href="{{ url('/install/device') }}">Back</a><button class="btn" type="submit">Install Now</button></div>
</form>
@endsection
