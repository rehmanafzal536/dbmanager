@extends('dbmanager::layout')
@section('content')

@php
$types = [
    'Numeric'  => ['INT','BIGINT','TINYINT','SMALLINT','MEDIUMINT','FLOAT','DOUBLE','DECIMAL','NUMERIC'],
    'String'   => ['VARCHAR','CHAR','TEXT','TINYTEXT','MEDIUMTEXT','LONGTEXT','ENUM','SET'],
    'Date/Time'=> ['DATE','DATETIME','TIMESTAMP','TIME','YEAR'],
    'Binary'   => ['BLOB','TINYBLOB','MEDIUMBLOB','LONGBLOB','BINARY','VARBINARY'],
    'Other'    => ['BOOLEAN','JSON','GEOMETRY'],
];
$flatTypes = array_merge(...array_values($types));
@endphp

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:800;color:#111827">Create Table</h1>
    <p style="font-size:13px;color:#6b7280;margin-top:2px">Define a new database table with full column options</p>
  </div>
  <a href="/dbmanager" class="btn btn-secondary btn-sm">&#8592; Back</a>
</div>

<div class="card">
  <div class="card-header">Table Definition</div>
  <div class="card-body">
    <form method="POST" action="/dbmanager/create-table" id="create-form">
      @csrf

      {{-- Table name --}}
      <div style="display:flex;gap:16px;align-items:flex-end;margin-bottom:24px;flex-wrap:wrap">
        <div style="min-width:260px;flex:1;max-width:360px">
          <label class="form-label">Table Name <span style="color:#dc2626">*</span></label>
          <input type="text" name="table_name" class="form-control" placeholder="my_table" required pattern="[a-zA-Z0-9_]+">
          <div class="form-hint">Letters, numbers, underscores only.</div>
        </div>
        <div style="min-width:200px">
          <label class="form-label">Number of columns</label>
          <div style="display:flex;gap:8px">
            <input type="number" id="num-cols" value="4" min="1" max="30"
              style="width:80px;padding:8px 10px;border:1px solid #d1d5db;border-radius:7px;font-size:13.5px">
            <button type="button" class="btn btn-secondary btn-sm" onclick="buildCols()">Go</button>
          </div>
        </div>
        <div style="color:#6b7280;font-size:12.5px;padding-bottom:4px">
          <code style="background:#f3f4f6;padding:2px 6px;border-radius:3px">id</code>,
          <code style="background:#f3f4f6;padding:2px 6px;border-radius:3px">created_at</code>,
          <code style="background:#f3f4f6;padding:2px 6px;border-radius:3px">updated_at</code> added automatically
        </div>
      </div>

      {{-- Column grid header --}}
      <div style="overflow-x:auto;margin-bottom:0">
        <table class="dt" id="cols-table" style="min-width:900px">
          <thead>
            <tr>
              <th style="width:36px">#</th>
              <th>Name <span style="color:#dc2626">*</span></th>
              <th style="width:140px">Type</th>
              <th style="width:90px">Length / Values</th>
              <th style="width:160px">Default</th>
              <th style="width:75px;text-align:center">Not Null</th>
              <th style="width:65px;text-align:center">Unique</th>
              <th style="width:75px;text-align:center">Auto Inc</th>
              <th style="width:65px;text-align:center">PK</th>
              <th style="width:32px"></th>
            </tr>
          </thead>
          <tbody id="cols-body">
            {{-- Rows built by JS --}}
          </tbody>
        </table>
      </div>

      <div style="padding:14px 0 4px;display:flex;gap:8px">
        <button type="button" class="btn btn-secondary btn-sm" onclick="addRow()">+ Add Column</button>
      </div>

      <div style="border-top:1px solid #e5e7eb;padding-top:16px;margin-top:8px;display:flex;gap:8px">
        <button type="submit" class="btn btn-success btn-sm">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          Create Table
        </button>
        <a href="/dbmanager" class="btn btn-secondary btn-sm">Cancel</a>
      </div>
    </form>
  </div>
</div>

@endsection
@section('scripts')
<script>
const TYPES = @json($flatTypes);
const GROUPED = @json($types);

let rowIdx = 0;

function typeOpts() {
    let html = '';
    for (const [group, types] of Object.entries(GROUPED)) {
        html += '<optgroup label="' + group + '">';
        types.forEach(t => html += '<option value="' + t + '">' + t + '</option>');
        html += '</optgroup>';
    }
    return html;
}

function addRow(name = '', type = 'VARCHAR', len = '255', defType = 'none', defVal = '', notnull = false, unique = false, autoInc = false, pk = false) {
    const i = rowIdx++;
    const tr = document.createElement('tr');
    tr.id = 'col-row-' + i;
    tr.innerHTML = `
        <td class="text-muted text-sm" style="text-align:center">${i + 1}</td>
        <td><input type="text" name="columns[${i}][name]" class="form-control" placeholder="column_name" value="${name}" required></td>
        <td>
            <select name="columns[${i}][type]" class="form-select" onchange="onTypeChange(this,${i})">
                ${typeOpts().replace('value="' + type + '"', 'value="' + type + '" selected')}
            </select>
        </td>
        <td><input type="text" name="columns[${i}][length]" id="len-${i}" class="form-control" placeholder="255" value="${len}"></td>
        <td>
            <select name="columns[${i}][default_type]" class="form-select" onchange="onDefChange(this,${i})" style="margin-bottom:4px">
                <option value="none"${defType==='none'?' selected':''}>None</option>
                <option value="null"${defType==='null'?' selected':''}>NULL</option>
                <option value="custom"${defType==='custom'?' selected':''}>Custom</option>
                <option value="current_timestamp"${defType==='current_timestamp'?' selected':''}>CURRENT_TIMESTAMP</option>
                <option value="empty"${defType==='empty'?' selected':''}>Empty string</option>
            </select>
            <input type="text" name="columns[${i}][default_val]" id="def-${i}" class="form-control"
                placeholder="value" value="${defVal}" style="display:${defType==='custom'?'block':'none'}">
        </td>
        <td style="text-align:center"><input type="checkbox" name="columns[${i}][notnull]" style="width:16px;height:16px;cursor:pointer"${notnull?' checked':''}></td>
        <td style="text-align:center"><input type="checkbox" name="columns[${i}][unique]" style="width:16px;height:16px;cursor:pointer"${unique?' checked':''}></td>
        <td style="text-align:center"><input type="checkbox" name="columns[${i}][auto_inc]" style="width:16px;height:16px;cursor:pointer"${autoInc?' checked':''}></td>
        <td style="text-align:center"><input type="checkbox" name="columns[${i}][pk]" style="width:16px;height:16px;cursor:pointer"${pk?' checked':''}></td>
        <td>
            <button type="button" class="btn btn-danger btn-xs" onclick="document.getElementById('col-row-${i}').remove();renumber()"
                style="width:28px;justify-content:center">&#x2715;</button>
        </td>`;
    document.getElementById('cols-body').appendChild(tr);
    onTypeChange(tr.querySelector('select[name*="[type]"]'), i);
}

function onTypeChange(sel, i) {
    const t   = sel.value.toUpperCase();
    const len = document.getElementById('len-' + i);
    if (!len) return;
    // Types that don't need length
    const noLen = ['TEXT','TINYTEXT','MEDIUMTEXT','LONGTEXT','BLOB','TINYBLOB','MEDIUMBLOB','LONGBLOB',
                   'DATE','DATETIME','TIMESTAMP','TIME','YEAR','BOOLEAN','JSON','INT','BIGINT',
                   'TINYINT','SMALLINT','MEDIUMINT','FLOAT','DOUBLE'];
    if (noLen.includes(t)) {
        len.value = '';
        len.placeholder = '—';
        len.disabled = true;
        len.style.background = '#f9fafb';
    } else if (t === 'ENUM' || t === 'SET') {
        len.disabled = false;
        len.style.background = '';
        len.placeholder = "'a','b','c'";
    } else {
        len.disabled = false;
        len.style.background = '';
        len.placeholder = t === 'DECIMAL' || t === 'NUMERIC' ? '10,2' : '255';
    }
}

function onDefChange(sel, i) {
    const inp = document.getElementById('def-' + i);
    if (inp) inp.style.display = sel.value === 'custom' ? 'block' : 'none';
}

function renumber() {
    document.querySelectorAll('#cols-body tr').forEach((tr, idx) => {
        const first = tr.querySelector('td:first-child');
        if (first) first.textContent = idx + 1;
    });
}

function buildCols() {
    const n = Math.max(1, Math.min(30, parseInt(document.getElementById('num-cols').value) || 4));
    document.getElementById('cols-body').innerHTML = '';
    rowIdx = 0;
    for (let i = 0; i < n; i++) addRow();
}

// Init with 4 rows
buildCols();
</script>
@endsection
