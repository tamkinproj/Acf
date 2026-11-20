@extends('install.layout')
@section('title','Install')
@section('body')
<h1>Install Insania</h1>
<p>Fill this in once. It creates the tables and your System Admin account.</p>
@if($errors)<div class="err">@foreach($errors as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<div class="card"><strong>Server check</strong>
@foreach($rows as $r)<div class="{{ $r['ok'] ? 'ok' : 'bad' }}">{{ $r['ok'] ? '✓' : '✗' }} {{ $r['label'] }} <small>{{ $r['detail'] }}</small></div>@endforeach</div>
<form method="post" autocomplete="off">
<div class="card"><h2>1. Setup code</h2>
<small>Open <code>{{ $hint }}</code> in File Manager, copy the text inside, paste it here. This proves you control the server.</small>
<label>Setup code</label><input name="token" required></div>
<div class="card"><h2>2. Foundation</h2>
<label>Foundation name</label><input name="foundation" required value="{{ $old['foundation'] ?? 'Al Insania Charity Foundation Inc.' }}">
<label>Website address of this API</label><input name="app_url" required placeholder="https://alinsania.com/server" value="{{ $old['app_url'] ?? '' }}">
<label>Address of the staff app (for browser access)</label><input name="cors" placeholder="https://alinsania.com" value="{{ $old['cors'] ?? '' }}"></div>
<div class="card"><h2>3. Database</h2><small>Create an empty MySQL database in hPanel first, then type its details.</small>
@unless($sqlite)<label>Host</label><input name="db_host" required value="{{ $old['db_host'] ?? 'localhost' }}"><label>Port</label><input name="db_port" value="{{ $old['db_port'] ?? '3306' }}">@endunless
<label>{{ $sqlite ? 'File path' : 'Database name' }}</label><input name="db_name" required value="{{ $old['db_name'] ?? '' }}">
@unless($sqlite)<label>User</label><input name="db_user" required value="{{ $old['db_user'] ?? '' }}"><label>Password</label><input type="password" name="db_pass">@endunless</div>
<div class="card"><h2>4. System Admin</h2>
<label>Full name</label><input name="admin_name" required value="{{ $old['admin_name'] ?? '' }}">
<label>Email</label><input type="email" name="admin_email" required value="{{ $old['admin_email'] ?? '' }}">
<label>Password</label><input type="password" name="admin_password" required><small>At least 12 characters with upper and lower case and a number.</small>
<label>Repeat password</label><input type="password" name="admin_password_confirmation" required></div>
<button>Install</button></form>
<details class="card" @if(collect($errors)->contains(fn ($e) => str_contains($e, 'already has tables'))) open @endif><summary><strong>Already installed before? Reconnect instead</strong></summary>
<p><small>Use this if you re-uploaded the files and the installer came back although your data is still there. It restores the settings file and changes <b>no data</b>. Your existing accounts keep working.</small></p>
<form method="post" action="install/reconnect" autocomplete="off">
<label>Setup code</label><input name="token" required>
<label>Website address of this API</label><input name="app_url" required placeholder="https://alinsania.com/server" value="{{ $old['app_url'] ?? '' }}">
<label>Address of the staff app (for browser access)</label><input name="cors" placeholder="https://alinsania.com,https://www.alinsania.com" value="{{ $old['cors'] ?? '' }}">
@unless($sqlite)<label>Database host</label><input name="db_host" required value="{{ $old['db_host'] ?? 'localhost' }}"><label>Port</label><input name="db_port" value="{{ $old['db_port'] ?? '3306' }}">@endunless
<label>{{ $sqlite ? 'File path' : 'Database name' }}</label><input name="db_name" required value="{{ $old['db_name'] ?? '' }}">
@unless($sqlite)<label>Database user</label><input name="db_user" required value="{{ $old['db_user'] ?? '' }}"><label>Database password</label><input type="password" name="db_pass">@endunless
<button>Reconnect</button></form></details>
@endsection
