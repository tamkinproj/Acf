@extends('install.layout')
@section('title','Upgrade')
@section('body')
<h1>Upgrade</h1>
@if($error)<div class="err">{{ $error }}</div>@endif
@if($result)<div class="card"><strong>Done ✓</strong><pre>{{ $result }}</pre></div>
@else
<p>Updates waiting: <strong>{{ $pending }}</strong>. Upload the new files first, then press the button. Your data is kept.</p>
<form method="post"><div class="card"><small>Open <code>{{ $hint }}</code> in File Manager and paste its text.</small>
<label>Setup code</label><input name="token" required value="{{ $code ?? '' }}"></div><button>Run upgrade</button></form>
@endif
<div class="card"><h2>Signed patch</h2>
<p class="note">Build on this server: <code>{{ $build ?? 'unknown' }}</code>. Upload the patch zip and its signature file. You get a summary first; nothing changes until you press Apply.</p>
<form method="post" action="upgrade/patch" enctype="multipart/form-data">
<label>Setup code</label><input name="token" required value="{{ $code ?? '' }}">
<label>Patch zip</label><input type="file" name="patch" accept=".zip" required>
<label>Signature file (.sig)</label><input type="file" name="signature" required>
<button>Check patch</button></form>
@if(!empty($undo))
<form method="post" action="upgrade/undo" style="margin-top:1rem" onsubmit="return confirm('Put the previous version back?')"><input type="hidden" name="id" value="{{ $undo['id'] }}">
<label>Undo the last update ({{ $undo['version'] }}, build {{ $undo['build'] }}). Setup code</label><input name="token" required><button>Undo last update</button></form>
@endif
</div>
@endsection
