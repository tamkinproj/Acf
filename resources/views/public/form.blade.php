@extends('public.layout')
@section('title', $schema['title'])
@section('content')
<h1>{{ $schema['title'] }}</h1>
@if($correction)
  <div class="alert warn" role="status"><strong>Please correct your registration.</strong><br>{{ $correction->review_note }}</div>
@elseif(!empty($schema['description']))
  <p class="lead">{!! nl2br(e($schema['description'])) !!}</p>
@endif

<form method="post" action="{{ $action }}" enctype="multipart/form-data" autocomplete="off" novalidate>
  @csrf
  <div class="trap" aria-hidden="true"><label for="website">Leave this empty</label><input id="website" name="website" type="text" tabindex="-1" autocomplete="off"></div>

  @foreach($schema['sections'] as $section)
    <fieldset class="group">
      <legend>{{ $section['title'] }}</legend>
      @if(!empty($section['description']))<p class="hint">{{ $section['description'] }}</p>@endif

      @foreach($section['fields'] as $f)
        @php
          $key = $f['key']; $id = 'q_'.$key; $name = 'f['.$key.']';
          $value = old('f.'.$key, $values[$key] ?? null);
          $err = $errors->first('f.'.$key) ?: $errors->first('files.'.$key);
          $options = $f['options'] ?? [];
        @endphp

        @if($f['type'] === 'multiple_choice' || $f['type'] === 'yes_no')
          <div class="q" role="radiogroup" aria-labelledby="{{ $id }}_l">
            <span class="ql" id="{{ $id }}_l">{{ $f['label'] }}@if($f['required'])<i class="req" aria-hidden="true">*</i>@endif</span>
            @if(!empty($f['help_text']))<span class="hint block">{{ $f['help_text'] }}</span>@endif
            <div class="choices">
              @foreach(($f['type'] === 'yes_no' ? ['yes' => 'Yes', 'no' => 'No'] : array_combine($options, $options)) as $val => $text)
                <label class="choice"><input type="radio" name="{{ $name }}" value="{{ $val }}" @checked((string) $value === (string) $val)> {{ ucfirst((string) $text) }}</label>
              @endforeach
            </div>
            @if($err)<div class="err">{{ $err }}</div>@endif
          </div>

        @elseif($f['type'] === 'checkbox')
          <div class="q">
            <span class="ql" id="{{ $id }}_l">{{ $f['label'] }}@if($f['required'])<i class="req" aria-hidden="true">*</i>@endif</span>
            @if(!empty($f['help_text']))<span class="hint block">{{ $f['help_text'] }}</span>@endif
            <div class="choices" role="group" aria-labelledby="{{ $id }}_l">
              @foreach($options as $opt)
                <label class="choice"><input type="checkbox" name="{{ $name }}[]" value="{{ $opt }}" @checked(in_array($opt, (array) $value, true))> {{ $opt }}</label>
              @endforeach
            </div>
            @if($err)<div class="err">{{ $err }}</div>@endif
          </div>

        @elseif($f['type'] === 'address')
          @php($parts = ['country' => 'Country', 'region' => 'Region', 'province' => 'Province', 'city' => 'City / municipality', 'barangay' => 'Barangay', 'address_detail' => 'Street, house number, landmarks'])
          <div class="q">
            <span class="ql">{{ $f['label'] }}@if($f['required'])<i class="req" aria-hidden="true">*</i>@endif</span>
            @if(!empty($f['help_text']))<span class="hint block">{{ $f['help_text'] }}</span>@endif
            <div class="row">
              @foreach(['country','region','province','city','barangay'] as $p)
                <div><label for="{{ $id }}_{{ $p }}">{{ $parts[$p] }}</label><input id="{{ $id }}_{{ $p }}" type="text" name="{{ $name }}[{{ $p }}]" maxlength="100" value="{{ is_array($value) ? ($value[$p] ?? '') : '' }}"></div>
              @endforeach
            </div>
            <label for="{{ $id }}_address_detail">{{ $parts['address_detail'] }}</label>
            <textarea id="{{ $id }}_address_detail" name="{{ $name }}[address_detail]" maxlength="2000" rows="2">{{ is_array($value) ? ($value['address_detail'] ?? '') : '' }}</textarea>
            @if($err)<div class="err">{{ $err }}</div>@endif
          </div>

        @else
          <div class="q">
            <label for="{{ $id }}">{{ $f['label'] }}@if($f['required'])<i class="req" aria-hidden="true">*</i>@endif</label>
            @if(!empty($f['help_text']))<span class="hint block">{{ $f['help_text'] }}</span>@endif

            @if($f['type'] === 'long_text')
              <textarea id="{{ $id }}" name="{{ $name }}" maxlength="5000" rows="4">{{ $value }}</textarea>
            @elseif($f['type'] === 'dropdown')
              <select id="{{ $id }}" name="{{ $name }}"><option value="">Choose…</option>@foreach($options as $opt)<option value="{{ $opt }}" @selected((string) $value === (string) $opt)>{{ ucfirst($opt) }}</option>@endforeach</select>
            @elseif($f['type'] === 'file_upload' || $f['type'] === 'photo')
              @if(!empty($files[$key]))<span class="hint block">Already received: {{ $files[$key] }}. Choose a file only if you want to replace it.</span>@endif
              <input id="{{ $id }}" type="file" name="files[{{ $key }}]" accept="{{ $f['type'] === 'photo' ? 'image/jpeg,image/png,image/webp' : 'application/pdf,image/jpeg,image/png,image/webp' }}">
              <span class="hint block">{{ $f['type'] === 'photo' ? 'JPEG, PNG or WebP' : 'PDF, JPEG, PNG or WebP' }}, up to {{ $maxMb }} MB.</span>
            @else
              @php($types = ['short_text' => 'text', 'number' => 'number', 'date' => 'date', 'phone' => 'tel', 'email' => 'email'])
              <input id="{{ $id }}" type="{{ $types[$f['type']] ?? 'text' }}" name="{{ $name }}" value="{{ is_scalar($value) ? $value : '' }}" @if($f['type'] === 'number')step="any" inputmode="decimal"@elseif($f['type'] === 'phone')inputmode="tel"@elseif($f['type'] === 'email')inputmode="email"@endif @if($f['type'] === 'date')max="{{ ($f['maps_to'] ?? '') === 'aytam.date_of_birth' ? now()->toDateString() : '2199-12-31' }}" min="1900-01-02"@else maxlength="255"@endif>
            @endif
            @if($err)<div class="err">{{ $err }}</div>@endif
          </div>
        @endif
      @endforeach
    </fieldset>
  @endforeach

  @if($errors->any())<p class="hint">If you chose files, please choose them again. For your safety they are not kept when a form needs fixing.</p>@endif
  <div class="actions"><span class="hint"><i class="req">*</i> required</span><button class="btn" type="submit">{{ $correction ? 'Send corrected registration' : 'Submit registration' }}</button></div>
</form>
@endsection
