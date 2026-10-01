<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="robots" content="noindex,nofollow">
<meta name="referrer" content="no-referrer">
<title>@yield('title', 'Registration') · {{ $foundation->short_name ?: $foundation->name }}</title>
<link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
<link rel="stylesheet" href="{{ asset('css/foundation.css') }}">
</head>
<body>
<div class="wrap">
  <div class="brand"><div class="mark">{{ mb_strtoupper(mb_substr($foundation->short_name ?: $foundation->name, 0, 1)) }}</div><div><b>REGISTRATION</b><span>{{ $foundation->name }}</span></div></div>
  <div class="card">
    @if($errors->any())
      <div class="alert bad" role="alert">
        <strong>Please check the form.</strong>
        <ul class="errlist">@foreach(collect($errors->all())->unique()->take(8) as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif
    @yield('content')
  </div>
  <p class="foot">This page is private to the people who were given the link. Do not share it.</p>
</div>
</body>
</html>
