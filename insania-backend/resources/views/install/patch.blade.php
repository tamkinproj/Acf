@extends('install.layout')
@section('title','Patch')
@section('body')
<h1>Patch</h1>
@if($error)<div class="err">{{ $error }}</div>@endif
@if($result)<div class="card"><strong>{{ $result }}</strong></div>@endif
@if($summary)
<div class="card"><h2>{{ $summary['name'] }} {{ $summary['version'] }}</h2>
<p>Build {{ $summary['current_build'] }} &rarr; {{ $summary['build'] }}</p>
@if($summary['notes'])<p>{{ $summary['notes'] }}</p>@endif
<p>Adds {{ $summary['add'] }}, changes {{ $summary['change'] }}, removes {{ $summary['delete'] }} files.@if($summary['database_changes']) It also changes the database.@endif</p>
<form method="post" action="apply"><input type="hidden" name="id" value="{{ $summary['id'] }}"><input type="hidden" name="token" value="{{ $token }}"><button>Apply update</button></form></div>
@endif
<p><a href="../upgrade">Back</a></p>
@endsection
