@extends('dbmanager::layout')
@section('content')

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px">
  <div>
    <h1 style="font-size:22px;font-weight:800;color:#111827">SQL Query</h1>
    <p style="font-size:13px;color:#6b7280;margin-top:3px">Run raw SQL against your database</p>
  </div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <button class="btn btn-secondary btn-sm" onclick="setQ('SELECT * FROM users LIMIT 10')">users</button>
    <button class="btn btn-secondary btn-sm" onclick="setQ('SELECT * FROM products LIMIT 20')">products</button>
    <button class="btn btn-secondary btn-sm" onclick="setQ('SELECT * FROM orders ORDER BY id DESC LIMIT 20')">orders</button>
    @if(config('database.default') === 'sqlite')
      <button class="btn btn-secondary btn-sm" onclick="setQ(&quot;SELECT name FROM sqlite_master WHERE type='table' ORDER BY name&quot;)">list tables</button>
    @else
      <button class="btn btn-secondary btn-sm" onclick="setQ('SHOW TABLES')">list tables</button>
    @endif
  </div>
</div>

<div class="card">
  <div class="card-header">
    <span style="display:flex;align-items:center;gap:8px">
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
      Query Editor
    </span>
    <span class="text-muted text-sm">Ctrl + Enter to run</span>
  </div>
  <div class="card-body">
    <form method="POST" action="/dbmanager/sql" id="sql-form">
      @csrf
      <textarea name="sql" id="sql-input" class="sql-editor" placeholder="SELECT * FROM users LIMIT 10;">{{ $sql ?? '' }}</textarea>
      <div style="margin-top:12px;display:flex;gap:8px;align-items:center">
        <button type="submit" class="btn btn-primary btn-sm">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
          Run Query
        </button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('sql-input').value=''">Clear</button>
      </div>
    </form>
  </div>
</div>

@if(isset($error))
<div class="alert alert-danger" style="font-family:monospace;font-size:13px">
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  {{ $error }}
</div>
@endif

@if(isset($result) && is_array($result))
<div class="card">
  <div class="card-header">
    <span style="display:flex;align-items:center;gap:8px">
      Results
      @if($affected !== null)
        <span style="background:#f0fdf4;color:#166534;font-size:12px;padding:2px 8px;border-radius:12px;font-weight:600">{{ count($result) }} rows</span>
      @endif
    </span>
    @if(count($result))
      <button class="btn btn-secondary btn-xs" onclick="exportCsv()">&#8595; Export CSV</button>
    @endif
  </div>
  @if(count($result))
  <div class="table-wrap">
    <table class="dt" id="result-table">
      <thead>
        <tr>@foreach(array_keys($result[0]) as $col)<th>{{ $col }}</th>@endforeach</tr>
      </thead>
      <tbody>
        @foreach($result as $row)
        <tr>
          @foreach($row as $val)
          <td title="{{ $val }}">
            @if($val === null)<span class="null-val">NULL</span>
            @else{{ Str::limit((string)$val, 80) }}@endif
          </td>
          @endforeach
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @else
  <div class="card-body text-muted text-sm">Query executed successfully — no rows returned.</div>
  @endif
</div>
@endif

@endsection
@section('scripts')
<script>
function setQ(q){document.getElementById('sql-input').value=q}
document.getElementById('sql-input').addEventListener('keydown',e=>{
    if((e.ctrlKey||e.metaKey)&&e.key==='Enter')document.getElementById('sql-form').submit()
});
function exportCsv(){
    const t=document.getElementById('result-table');if(!t)return;
    const rows=[...t.querySelectorAll('tr')].map(r=>[...r.querySelectorAll('th,td')].map(c=>'"'+c.innerText.replace(/"/g,'""')+'"').join(','));
    const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([rows.join('\n')],{type:'text/csv'}));a.download='query_result.csv';a.click();
}
</script>
@endsection
