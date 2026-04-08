@extends('dbmanager::layout')
@section('content')
@php $totalRows = array_sum($tables); $totalTables = count($tables); @endphp

{{-- Header --}}
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px">
  <div>
    <h1 style="font-size:22px;font-weight:800;color:#111827">Database Overview</h1>
    <p style="font-size:13px;color:#6b7280;margin-top:3px">{{ strtoupper(config('database.default')) }} &middot; {{ $dbSize }}</p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="/dbmanager/backup" class="btn btn-secondary btn-sm">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Backup DB
    </a>
    <button class="btn btn-secondary btn-sm" onclick="toggle('restore-panel')">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
      Restore DB
    </button>
    <a href="/dbmanager/import" class="btn btn-secondary btn-sm">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      Import
    </a>
    <a href="/dbmanager/create-table" class="btn btn-success btn-sm">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
      New Table
    </a>
    <a href="/dbmanager/sql" class="btn btn-primary btn-sm">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
      SQL Query
    </a>
  </div>
</div>

{{-- Restore panel --}}
<div id="restore-panel" style="display:none;margin-bottom:20px">
  <div class="card">
    <div class="card-header">
      Restore Database
      <button class="btn btn-secondary btn-xs" onclick="toggle('restore-panel')">&#x2715; Close</button>
    </div>
    <div class="card-body">
      <form method="POST" action="/dbmanager/restore" enctype="multipart/form-data" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        @csrf
        <div style="flex:1;min-width:240px">
          <label class="form-label">Backup File</label>
          <input type="file" name="db_file" class="form-control">
          <div class="form-hint">Accepts .sqlite or .sql files</div>
        </div>
        <button class="btn btn-warning btn-sm" onclick="return confirm('This will replace the current database. Are you sure?')">Restore Now</button>
      </form>
    </div>
  </div>
</div>

{{-- Stats --}}
<div class="stats-grid">
  <div class="stat-box">
    <div class="stat-icon blue">
      <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/></svg>
    </div>
    <div><div class="stat-val">{{ $totalTables }}</div><div class="stat-lbl">Total Tables</div></div>
  </div>
  <div class="stat-box">
    <div class="stat-icon green">
      <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
    </div>
    <div><div class="stat-val">{{ number_format($totalRows) }}</div><div class="stat-lbl">Total Rows</div></div>
  </div>
  <div class="stat-box">
    <div class="stat-icon amber">
      <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4.03 3 9 3s9-1.34 9-3"/></svg>
    </div>
    <div><div class="stat-val">{{ $dbSize }}</div><div class="stat-lbl">Database Size</div></div>
  </div>
  <div class="stat-box">
    <div class="stat-icon cyan">
      <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
    </div>
    <div><div class="stat-val">{{ strtoupper(config('database.default')) }}</div><div class="stat-lbl">Driver</div></div>
  </div>
</div>

{{-- Tables list --}}
@if($totalTables)
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
  <div style="font-size:16px;font-weight:700;color:#111827">All Tables <span style="font-size:13px;color:#6b7280;font-weight:400">{{ $totalTables }} tables</span></div>
  <a href="/dbmanager/create-table" class="btn btn-secondary btn-sm">+ Add Table</a>
</div>
<div class="card">
  <div class="table-wrap">
    <table class="dt">
      <thead>
        <tr>
          <th style="width:44px">#</th>
          <th>Table Name</th>
          <th style="width:120px;text-align:right">Rows</th>
          <th style="width:280px;text-align:right">Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach($tables as $t => $cnt)
        <tr>
          <td class="text-muted text-sm">{{ $loop->iteration }}</td>
          <td>
            <a href="/dbmanager/table/{{ $t }}" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit">
              <span style="width:32px;height:32px;background:#eff6ff;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#1d4ed8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/></svg>
              </span>
              <span style="font-weight:600;color:#111827;font-size:14px">{{ $t }}</span>
            </a>
          </td>
          <td style="text-align:right">
            <span style="font-size:15px;font-weight:700;color:{{ $cnt > 0 ? '#1d4ed8' : '#9ca3af' }}">{{ number_format($cnt) }}</span>
          </td>
          <td style="text-align:right">
            <div style="display:flex;gap:6px;justify-content:flex-end">
              <a href="/dbmanager/table/{{ $t }}" class="btn btn-secondary btn-xs">Browse</a>
              <a href="/dbmanager/table/{{ $t }}/structure" class="btn btn-secondary btn-xs">Structure</a>
              <a href="/dbmanager/table/{{ $t }}/export/csv" class="btn btn-secondary btn-xs">Export</a>
              <form method="POST" action="/dbmanager/drop-table/{{ $t }}" style="display:inline">
                @csrf @method('DELETE')
                <button class="btn btn-danger btn-xs" onclick="return confirm('Drop table \'{{ $t }}\'? This cannot be undone.')">Drop</button>
              </form>
            </div>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@else
<div class="card">
  <div class="card-body" style="text-align:center;padding:64px 20px">
    <div style="width:64px;height:64px;background:#eff6ff;border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
      <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#1d4ed8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4.03 3 9 3s9-1.34 9-3"/></svg>
    </div>
    <div style="font-size:16px;font-weight:700;color:#111827;margin-bottom:6px">No tables yet</div>
    <div style="font-size:13.5px;color:#6b7280;margin-bottom:20px">Create your first table to get started.</div>
    <a href="/dbmanager/create-table" class="btn btn-primary btn-sm">Create Table</a>
  </div>
</div>
@endif

@endsection
@section('scripts')
<script>
function toggle(id){const el=document.getElementById(id);el.style.display=el.style.display==='none'?'block':'none'}
</script>
@endsection
