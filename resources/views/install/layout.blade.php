<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>@yield('title', 'Setup') · Foundation Management System</title>
<link rel="stylesheet" href="/css/foundation.css">
</head>
<body>
<div class="wrap">
  <div class="brand"><div class="mark">F</div><div><b>FOUNDATION</b><span>Foundation Management System</span></div></div>
  <div class="card">
    @isset($current)
      @php($order = ['requirements'=>'Requirements','database'=>'Database','system'=>'System','foundation'=>'Foundation','admin'=>'Administrator','device'=>'Device'])
      @php($keys = array_keys($order))
      @php($idx = array_search($current, $keys, true))
      <ol class="steps" aria-label="Setup progress">
        @foreach($keys as $i => $k)
          <li class="{{ $idx === false ? 'done' : ($i < $idx ? 'done' : ($i === $idx ? 'now' : '')) }}"></li>
        @endforeach
      </ol>
      <div class="steplabel">@if($idx !== false)Step {{ $idx + 1 }} of {{ count($keys) }} — {{ $order[$current] }}@else Review @endif</div>
    @endisset
    @if(isset($errors) && $errors->any())
      <div class="alert bad" role="alert">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
      </div>
    @endif
    @yield('content')
  </div>
</div>
</body>
</html>
