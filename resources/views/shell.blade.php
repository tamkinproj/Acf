<!doctype html>
<html lang="{{ app()->getLocale() }}" data-base="{{ rtrim(url('/'), '/') }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#0A4A3C">
<meta name="robots" content="noindex,nofollow">
<title>{{ $foundation->name ?? config('app.name') }}</title>
<link rel="icon" type="image/png" href="{{ asset('icons/favicon-32.png') }}">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
<link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
<script type="module" src="{{ asset('app/main.js') }}"></script>
</head>
<body>
<div id="app" class="boot" role="status" aria-live="polite">
  <svg class="boot-mark" viewBox="0 0 64 64" aria-hidden="true"><path d="M32 2 L40.5 13 L54 10 L51 23.5 L62 32 L51 40.5 L54 54 L40.5 51 L32 62 L23.5 51 L10 54 L13 40.5 L2 32 L13 23.5 L10 10 L23.5 13Z"/></svg>
  <p>{{ $foundation->name ?? config('app.name') }}</p>
  <noscript><p class="boot-note">This app needs JavaScript to run. Please enable it in your browser.</p></noscript>
</div>
</body>
</html>
