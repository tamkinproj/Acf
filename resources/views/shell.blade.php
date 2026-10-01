<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $foundation->name ?? config('app.name') }}</title>
<link rel="stylesheet" href="/css/foundation.css">
</head>
<body>
<div class="wrap">
  <div class="brand"><div class="mark">F</div><div><b>FOUNDATION</b><span>{{ $foundation->name ?? config('app.name') }}</span></div></div>
  <div class="card center">
    <h1>System is running</h1>
    <p class="lead">The server and sync API are ready. The web client is the next build phase.</p>
    <p class="hint">Version {{ config('foundation.version') }}</p>
  </div>
</div>
</body>
</html>
