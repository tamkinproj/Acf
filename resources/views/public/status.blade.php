@extends('public.layout')
@section('title','Registration status')
@section('content')
<h1>Registration {{ $registration->reference }}</h1>
@if(session('resubmitted'))<div class="alert good" role="status">Thank you — your corrections were sent. The foundation will look at them again.</div>@endif
<dl class="kv">
  <dt>Received</dt><dd>{{ $registration->submitted_at->toFormattedDayDateString() }}</dd>
  <dt>Child</dt><dd>{{ $registration->applicant_name ?: '—' }}</dd>
  <dt>Status</dt>
  <dd>
    @if($registration->status === 'approved') <strong>Approved</strong>
    @elseif($registration->status === 'needs_correction') <strong>Needs correction</strong>
    @else <strong>Waiting for review</strong> @endif
  </dd>
  @if($registration->status === 'approved' && $registration->aytam_id)
    @php($code = \App\Models\Aytam::query()->whereKey($registration->aytam_id)->value('aytam_code'))
    @if($code)<dt>Reference number</dt><dd><code>{{ $code }}</code></dd>@endif
  @endif
</dl>
@if($registration->status === 'approved')
  <p class="lead">The registration was approved. The foundation will be in touch about the next steps.</p>
@else
  <p class="lead">The foundation is reviewing this registration. Check back on this page for the result.</p>
@endif
@endsection
