@extends('install.layout')
@section('title','Upgrade')
@section('body')
<h1>Upgrade</h1>
@if($error)<div class="err">{{ $error }}</div>@endif
@if($result)<div class="card"><strong>Done ✓</strong><pre>{{ $result }}</pre></div>
@else
<p>Updates waiting: <strong>{{ $pending }}</strong>. Upload the new files first, then press the button. Your data is kept.</p>
<form method="post" action="/upgrade"><div class="card"><small>Open <code>{{ $hint }}</code> in File Manager and paste its text.</small>
<label>Setup code</label><input name="token" required></div><button>Run upgrade</button></form>
@endif
@endsection
