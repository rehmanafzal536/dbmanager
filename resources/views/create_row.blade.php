@extends('dbmanager::layout')
@section('content')

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px">
  <div>
    <h1 style="font-size:22px;font-weight:800;color:#111827">Insert Row</h1>
    <p style="font-size:13px;color:#6b7280;margin-top:3px">
      <a href="/dbmanager/table/{{ $table }}" style="color:#1d4ed8;text-decoration:none">{{ $table }}</a>
      &rsaquo; New Row
    </p>
  </div>
  <a href="/dbmanager/table/{{ $table }}" class="btn btn-secondary btn-sm">&#8592; Back</a>
</div>

<div class="card" style="max-width:900px">
  <div class="card-header">
    <span style="display:flex;align-items:center;gap:8px">
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
      New row in {{ $table }}
    </span>
  </div>
  <div class="card-body">
    <form method="POST" action="/dbmanager/table/{{ $table }}/store">
      @csrf
      <div class="form-grid form-grid-2">
        @foreach($cols as $col)
          @if(empty($col['pk']))
          <div>
            <label class="form-label">
              {{ $col['name'] }}
              <span style="color:#9ca3af;font-weight:400;text-transform:none;letter-spacing:0;margin-left:4px;font-size:11px">{{ $col['type'] ?: 'TEXT' }}</span>
              @if($col['notnull'])<span style="color:#dc2626;margin-left:2px">*</span>@endif
            </label>
            <input type="text" name="{{ $col['name'] }}" value="{{ $col['dflt_value'] ?? '' }}" class="form-control" placeholder="{{ $col['dflt_value'] ?? 'NULL' }}">
          </div>
          @endif
        @endforeach
      </div>
      <div style="margin-top:22px;padding-top:18px;border-top:1px solid #e5e7eb;display:flex;gap:8px">
        <button type="submit" class="btn btn-success btn-sm">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
          Insert Row
        </button>
        <a href="/dbmanager/table/{{ $table }}" class="btn btn-secondary btn-sm">Cancel</a>
      </div>
    </form>
  </div>
</div>
@endsection
