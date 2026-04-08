@extends('dbmanager::layout')
@section('content')

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px">
  <div>
    <h1 style="font-size:22px;font-weight:800;color:#111827">Edit Row</h1>
    <p style="font-size:13px;color:#6b7280;margin-top:3px">
      <a href="/dbmanager/table/{{ $table }}" style="color:#1d4ed8;text-decoration:none">{{ $table }}</a>
      &rsaquo; Row #{{ $row[$pk] }}
    </p>
  </div>
  <a href="/dbmanager/table/{{ $table }}" class="btn btn-secondary btn-sm">&#8592; Back</a>
</div>

<div class="card" style="max-width:900px">
  <div class="card-header">
    <span style="display:flex;align-items:center;gap:8px">
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      Editing {{ $table }} — Row #{{ $row[$pk] }}
    </span>
  </div>
  <div class="card-body">
    <form method="POST" action="/dbmanager/table/{{ $table }}/update/{{ $row[$pk] }}">
      @csrf @method('PUT')
      <div class="form-grid form-grid-2">
        @foreach($cols as $col)
        <div>
          <label class="form-label">
            {{ $col['name'] }}
            @if($col['pk'])<span class="badge badge-pk" style="margin-left:4px;font-size:10px">PK</span>@endif
            <span style="color:#9ca3af;font-weight:400;text-transform:none;letter-spacing:0;margin-left:4px;font-size:11px">{{ $col['type'] ?: 'TEXT' }}</span>
            @if($col['notnull'] && !$col['pk'])<span style="color:#dc2626;margin-left:2px">*</span>@endif
          </label>
          @php
            $val    = $row[$col['name']] ?? '';
            $isLong = strlen((string)$val) > 80 || in_array($col['name'], ['description','content','body','address','note','message','payload','order_note']);
          @endphp
          @if($col['pk'])
            <input type="text" value="{{ $val }}" class="form-control" disabled style="background:#f9fafb;color:#6b7280;cursor:not-allowed">
          @elseif($isLong)
            <textarea name="{{ $col['name'] }}" class="form-control" rows="3" style="resize:vertical">{{ $val }}</textarea>
          @else
            <input type="text" name="{{ $col['name'] }}" value="{{ $val }}" class="form-control" placeholder="NULL">
          @endif
        </div>
        @endforeach
      </div>
      <div style="margin-top:22px;padding-top:18px;border-top:1px solid #e5e7eb;display:flex;gap:8px">
        <button type="submit" class="btn btn-primary btn-sm">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          Save Changes
        </button>
        <a href="/dbmanager/table/{{ $table }}" class="btn btn-secondary btn-sm">Cancel</a>
      </div>
    </form>
  </div>
</div>
@endsection
