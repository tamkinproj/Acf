@extends('install.layout')
@section('title','Update')
@section('content')
<h1>Update needed</h1>
<p class="lead">New program files were uploaded. Finish the update to bring your data up to date. Nothing is deleted.</p>
<dl class="kv">
  <dt>Installed</dt><dd>{{ $from }}</dd>
  <dt>New version</dt><dd>{{ $to }}</dd>
</dl>
@if($needsAdmin)
<div class="alert warn">This release introduces the <strong>Platform Admin</strong>, who creates and manages foundations. Your existing Super Admin accounts become Foundation Admins of your foundation. Choose the Platform Admin account now.</div>
@endif
<form method="post" action="{{ url('/upgrade/run') }}" autocomplete="off">
  @csrf
  @if($needsAdmin)
  <label for="name">Platform Admin name</label>
  <input id="name" name="name" type="text" required value="{{ old('name') }}" autocomplete="name">
  <label for="email">Email</label>
  <input id="email" name="email" type="email" required value="{{ old('email') }}" autocomplete="username">
  <div class="row">
    <div><label for="admin_password">Password <span class="hint">at least {{ $minLength }} characters, with letters and numbers</span></label>
      <input id="admin_password" name="admin_password" type="password" required autocomplete="new-password"></div>
    <div><label for="admin_password_confirmation">Confirm password</label>
      <input id="admin_password_confirmation" name="admin_password_confirmation" type="password" required autocomplete="new-password"></div>
  </div>
  @endif
  <div class="actions"><span></span><button class="btn" type="submit">Update now</button></div>
</form>
@endsection
