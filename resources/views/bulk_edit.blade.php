@extends('dbmanager::layout')
@section('content')

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:800;color:#111827">Edit {{ count($rows) }} Row{{ count($rows) > 1 ? 's' : '' }}</h1>
    <p style="font-size:13px;color:#6b7280;margin-top:2px">
      <a href="/dbmanager/table/{{ $table }}" style="color:#1d4ed8;text-decoration:none">{{ $table }}</a>
      &rsaquo; Bulk Edit
    </p>
  </div>
  <a href="/dbmanager/table/{{ $table }}" class="btn btn-secondary btn-sm">&#8592; Cancel</a>
</div>

<form method="POST" action="/dbmanager/table/{{ $table }}/bulk-update">
  @csrf

  @foreach($rows as $row)
  @php $rowId = $row[$pk]; @endphp
  <div class="card">
    <div class="card-header">
      <span style="display:flex;align-items:center;gap:8px">
        <span class="badge badge-pk">{{ $pk }}</span>
        <span style="font-weight:700;color:#111827">{{ $rowId }}</span>
      </span>
      <span class="text-muted text-sm">Row #{{ $loop->iteration }} of {{ count($rows) }}</span>
    </div>
    <div class="card-body">
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px">
        @foreach($cols as $col)
        @php
          $colName = $col['name'];
          $rawType = strtolower($col['type'] ?? 'text');
          $val     = $row[$colName] ?? '';
          $isPk    = $col['pk'];
          $inputName = "rows[{$rowId}][{$colName}]";
        @endphp
        <div>
          <label class="form-label">
            {{ $colName }}
            @if($isPk)<span class="badge badge-pk" style="margin-left:4px;font-size:10px">PK</span>@endif
            <span style="color:#9ca3af;font-weight:400;text-transform:none;letter-spacing:0;margin-left:4px;font-size:11px">{{ $col['type'] ?: 'TEXT' }}</span>
            @if($col['notnull'] && !$isPk)<span style="color:#dc2626;margin-left:2px">*</span>@endif
          </label>

          @if($isPk)
            <input type="text" value="{{ $val }}" class="form-control" disabled style="background:#f9fafb;color:#6b7280;cursor:not-allowed">
          @elseif(str_starts_with($rawType, 'enum'))
            @php preg_match('/enum\(([^)]+)\)/', $rawType, $em); $opts = $em ? array_map(fn($o) => trim($o, "'\" "), explode(',', $em[1])) : []; @endphp
            <select name="{{ $inputName }}" class="form-select">
              <option value="">(NULL)</option>
              @foreach($opts as $o)
                <option value="{{ $o }}" {{ $val === $o ? 'selected' : '' }}>{{ $o }}</option>
              @endforeach
            </select>
          @elseif($rawType === 'tinyint(1)' || $rawType === 'boolean' || $rawType === 'bool')
            <select name="{{ $inputName }}" class="form-select">
              <option value="" {{ $val === null ? 'selected' : '' }}>(NULL)</option>
              <option value="0" {{ (string)$val === '0' ? 'selected' : '' }}>0 (false)</option>
              <option value="1" {{ (string)$val === '1' ? 'selected' : '' }}>1 (true)</option>
            </select>
          @elseif(str_contains($rawType, 'datetime') || str_contains($rawType, 'timestamp'))
            <input type="datetime-local" name="{{ $inputName }}" value="{{ $val ? str_replace(' ', 'T', substr($val, 0, 16)) : '' }}" class="form-control">
          @elseif(str_contains($rawType, 'date') && !str_contains($rawType, 'datetime'))
            <input type="date" name="{{ $inputName }}" value="{{ $val }}" class="form-control">
          @elseif(str_contains($rawType, 'time') && !str_contains($rawType, 'datetime'))
            <input type="time" name="{{ $inputName }}" value="{{ $val }}" class="form-control">
          @elseif(preg_match('/int|float|double|decimal|numeric|real/', $rawType))
            <input type="number" {{ preg_match('/float|double|decimal|numeric|real/', $rawType) ? 'step="any"' : '' }}
              name="{{ $inputName }}" value="{{ $val }}" class="form-control">
          @elseif(preg_match('/text|blob|json/', $rawType))
            <textarea name="{{ $inputName }}" class="form-control" rows="2" style="resize:vertical">{{ $val }}</textarea>
          @else
            <input type="text" name="{{ $inputName }}" value="{{ $val }}" class="form-control" placeholder="NULL">
          @endif
        </div>
        @endforeach
      </div>
    </div>
  </div>
  @endforeach

  <div style="position:sticky;bottom:0;background:#fff;border-top:1px solid #e5e7eb;padding:14px 0;display:flex;gap:10px;z-index:5">
    <button type="submit" class="btn btn-primary btn-sm">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
      Save All {{ count($rows) }} Row{{ count($rows) > 1 ? 's' : '' }}
    </button>
    <a href="/dbmanager/table/{{ $table }}" class="btn btn-secondary btn-sm">Cancel</a>
  </div>
</form>

@endsection
