@extends('install.layout')
@section('title','Installer access')
@section('content')
<h1>Installer access</h1>
<p class="lead">To prove you control this server, enter the one-time setup token.</p>
<div class="alert warn">Open the file <code>{{ $path }}</code> on the server (File Manager, FTP or SSH) and copy its contents here. The file is deleted when setup completes.</div>
<form method="post" action="/install/token" autocomplete="off">
  @csrf
  <label for="token">Setup token</label>
  <input id="token" name="token" type="text" required autofocus autocomplete="off" spellcheck="false">
  <div class="actions"><span></span><button class="btn" type="submit">Continue</button></div>
</form>
@endsection
