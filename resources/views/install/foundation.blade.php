@extends('install.layout')
@section('title','Foundation')
@section('content')
<h1>Foundation profile</h1>
<p class="lead">Tell us about your foundation. This creates its organization profile.</p>
<form method="post" action="/install/foundation" enctype="multipart/form-data">
  @csrf
  <label for="name">Foundation name</label>
  <input id="name" name="name" type="text" required value="{{ old('name', $v['name'] ?? '') }}">
  <label for="short_name">Short name</label>
  <input id="short_name" name="short_name" type="text" value="{{ old('short_name', $v['short_name'] ?? '') }}">
  <label for="description">Description</label>
  <textarea id="description" name="description">{{ old('description', $v['description'] ?? '') }}</textarea>
  <label for="address">Address</label>
  <textarea id="address" name="address">{{ old('address', $v['address'] ?? '') }}</textarea>
  <div class="row">
    <div><label for="phone">Contact number</label><input id="phone" name="phone" type="text" value="{{ old('phone', $v['phone'] ?? '') }}"></div>
    <div><label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email', $v['email'] ?? '') }}"></div>
  </div>
  <label for="website">Website</label>
  <input id="website" name="website" type="url" value="{{ old('website', $v['website'] ?? '') }}">
  <label for="logo">Logo <span class="hint">PNG, JPEG or WebP, up to 2 MB{{ !empty($v['logo']) ? ' — a logo is already saved; choose a file only to replace it' : '' }}</span></label>
  <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp">
  <div class="actions"><a class="btn ghost" href="/install/system">Back</a><button class="btn" type="submit">Continue</button></div>
</form>
@endsection
