<!doctype html>
<html lang="{{ app()->getLocale() }}" data-base="{{ rtrim(url('/'), '/') }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#f5f5f7" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#000000" media="(prefers-color-scheme: dark)">
<meta name="robots" content="noindex,nofollow">
<title>{{ $foundation->name ?? config('app.name') }}</title>
<link rel="icon" type="image/png" href="{{ asset('icons/favicon-32.png') }}">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
<link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
<script type="module" src="{{ asset('app/main.js') }}"></script>
</head>
<body>
<div id="app" class="boot" role="status" aria-live="polite">
  <span class="boot-spinner" aria-hidden="true"></span>
  <p>{{ $foundation->name ?? config('app.name') }}</p>
  <noscript><p class="boot-note">This app needs JavaScript to run. Please enable it in your browser.</p></noscript>
</div>
</body>
</html>
