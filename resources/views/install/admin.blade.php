@extends('install.layout')
@section('title','Administrator')
@section('content')
<h1>Administrator account</h1>
<p class="lead">This account becomes the <strong>Super Administrator</strong>. There is no default password — you choose it now.</p>
<form method="post" action="{{ url('/install/admin') }}" autocomplete="off">
  @csrf
  <label for="name">Full name</label>
  <input id="name" name="name" type="text" required value="{{ old('name', $v['name'] ?? '') }}" autocomplete="name">
  <label for="email">Email</label>
  <input id="email" name="email" type="email" required value="{{ old('email', $v['email'] ?? '') }}" autocomplete="username">
  <div class="row">
    <div><label for="admin_password">Password <span class="hint">at least {{ $minLength }} characters, with letters and numbers</span></label>
      <input id="admin_password" name="admin_password" type="password" required autocomplete="new-password"></div>
    <div><label for="admin_password_confirmation">Confirm password</label>
      <input id="admin_password_confirmation" name="admin_password_confirmation" type="password" required autocomplete="new-password"></div>
  </div>
  <div class="actions"><a class="btn ghost" href="{{ url('/install/foundation') }}">Back</a><button class="btn" type="submit">Continue</button></div>
</form>
@endsection
