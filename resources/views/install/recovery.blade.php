@extends('install.layout')
@section('title','Recovery')
@section('content')
<h1>Installation needs attention</h1>
<div class="alert bad" role="alert">{{ $reason }}</div>
<p>The system has stopped to protect your data. <strong>Nothing has been changed or deleted.</strong></p>
<ul>
  <li>If you restored from a backup, restore <code>storage/app/install/installed.lock</code> and the <code>.env</code> file from the same backup.</li>
  <li>If the application key (<code>APP_KEY</code>) changed, restore the original key.</li>
  <li>The installation log is at <code>storage/logs/install.log</code>.</li>
</ul>
<p class="hint">The installer will not run again automatically. Contact the person who administers this server.</p>
@endsection
