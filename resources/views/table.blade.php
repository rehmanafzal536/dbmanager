@extends('dbmanager::layout')
@section('content')
@php
  $pk      = collect($cols)->where('pk',1)->pluck('name')->first() ?? 'id';
  $colMeta = collect($cols)->keyBy('name');
@endphp

{{-- Header --}}
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px">
  <div>
    <h1 style="font-size:20px;font-weight:800;color:#111827">{{ $table }}</h1>
    <p style="font-size:13px;color:#6b7280;margin-top:2px">{{ number_format($total) }} rows &mdash; double-click any cell to edit inline</p>
  </div>
  <div style="display:flex;gap:7px;flex-wrap:wrap">
    <a href="/dbmanager/table/{{ $table }}/structure" class="btn btn-secondary btn-sm">Structure</a>
    <a href="/dbmanager/table/{{ $table }}/create" class="btn btn-success btn-sm">+ Insert Row</a>
    <a href="/dbmanager/table/{{ $table }}/export/csv" class="btn btn-secondary btn-sm">&#8595; CSV</a>
    <a href="/dbmanager/table/{{ $table }}/export/sql" class="btn btn-secondary btn-sm">&#8595; SQL</a>
    <button class="btn btn-secondary btn-sm" onclick="togglePanel('import-panel')">&#8593; Import CSV</button>
    <form method="POST" action="/dbmanager/table/{{ $table }}/truncate" style="display:inline">
      @csrf @method('DELETE')
      <button class="btn btn-danger btn-sm" onclick="return confirm('Truncate ALL rows from {{ $table }}?')">Truncate</button>
    </form>
  </div>
</div>

{{-- Import CSV --}}
<div id="import-panel" style="display:none;margin-bottom:14px">
  <div class="card">
    <div class="card-header">Import CSV<button class="btn btn-secondary btn-xs" onclick="togglePanel('import-panel')" style="margin-left:auto">&#x2715;</button></div>
    <div class="card-body">
      <form method="POST" action="/dbmanager/table/{{ $table }}/import/csv" enctype="multipart/form-data" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        @csrf
        <div style="flex:1;min-width:220px">
          <label class="form-label">CSV File</label>
          <input type="file" name="csv_file" accept=".csv" class="form-control">
        </div>
        <button class="btn btn-success btn-sm">Import</button>
      </form>
    </div>
  </div>
</div>

{{-- Search + bulk bar --}}
<div class="card" style="margin-bottom:14px">
  <div class="card-body" style="padding:12px 16px">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
      <form method="GET" style="display:flex;gap:8px;flex:1;flex-wrap:wrap;align-items:flex-end" id="search-form">
        <div style="flex:1;min-width:180px">
          <label class="form-label">Search</label>
          <input type="text" name="search" value="{{ $search }}" placeholder="Search all columns..." class="form-control">
        </div>
        <div style="width:90px">
          <label class="form-label">Per page</label>
          <select name="per_page" class="form-select" data-autosubmit>
            @foreach([25,50,100,250] as $n)
              <option value="{{ $n }}" {{ $perPage==$n?'selected':'' }}>{{ $n }}</option>
            @endforeach
          </select>
        </div>
        <button class="btn btn-primary btn-sm">Search</button>
        @if($search)<a href="/dbmanager/table/{{ $table }}" class="btn btn-secondary btn-sm">Clear</a>@endif
      </form>
    </div>

    {{-- Bulk action bar (shown when rows selected) --}}
    <div id="bulk-bar" style="display:none;margin-top:10px;padding:10px 14px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;align-items:center;gap:10px;flex-wrap:wrap">
      <span id="bulk-count" style="font-size:13px;font-weight:700;color:#92400e">0 selected</span>
      <button class="btn btn-primary btn-sm" onclick="openBulkEditPage()">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Edit Selected Rows
      </button>
      <button class="btn btn-danger btn-sm" onclick="bulkDelete()">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
        Delete Selected
      </button>
      <button class="btn btn-secondary btn-sm" onclick="clearSelection()">Deselect All</button>
    </div>
  </div>
</div>

{{-- Hidden forms --}}
<form id="bulk-delete-form" method="POST" action="/dbmanager/table/{{ $table }}/bulk-delete" style="display:none">
  @csrf @method('DELETE')
  <div id="bulk-delete-ids"></div>
</form>

@if(count($rows))
<div class="card">
  <div class="table-wrap">
    <table class="dt" id="data-table">
      <thead>
        <tr>
          <th style="width:36px"><input type="checkbox" id="chk-all" onchange="toggleAll(this)" style="cursor:pointer;width:15px;height:15px"></th>
          <th style="width:40px;color:#9ca3af">#</th>
          @foreach($colNames as $col)
          <th>
            <a href="?search={{ urlencode($search) }}&per_page={{ $perPage }}&order_by={{ $col }}&order_dir={{ $orderBy==$col && $orderDir=='ASC' ? 'DESC' : 'ASC' }}">
              {{ $col }}@if($orderBy==$col){{ $orderDir=='ASC' ? ' ↑' : ' ↓' }}@endif
            </a>
          </th>
          @endforeach
          <th style="width:90px">Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach($rows as $i => $row)
        <tr data-pk="{{ $row[$pk] }}" id="row-{{ $row[$pk] }}">
          <td onclick="event.stopPropagation()">
            <input type="checkbox" class="row-chk" value="{{ $row[$pk] }}" onchange="updateBulkBar()" style="cursor:pointer;width:15px;height:15px">
          </td>
          <td class="text-muted text-sm">{{ ($page-1)*$perPage+$i+1 }}</td>
          @foreach($row as $colName => $val)
          @php
            $meta    = $colMeta->get($colName);
            $rawType = strtolower($meta['type'] ?? 'text');
            $isPk    = $meta && $meta['pk'];
          @endphp
          <td class="editable-cell {{ $isPk ? 'pk-cell' : '' }}"
              data-col="{{ $colName }}"
              data-pk-val="{{ $row[$pk] }}"
              data-type="{{ $rawType }}"
              data-val="{{ $val }}"
              ondblclick="{{ $isPk ? '' : 'startInlineEdit(this)' }}"
              title="{{ $isPk ? 'Primary key — cannot edit' : 'Double-click to edit' }}"
              style="{{ $isPk ? '' : 'cursor:pointer;' }}">
            @if($val === null)
              <span class="null-val">NULL</span>
            @else
              <span class="cell-display">{{ Str::limit((string)$val, 60) }}</span>
            @endif
          </td>
          @endforeach
          <td onclick="event.stopPropagation()">
            <div class="row-actions">
              <a href="/dbmanager/table/{{ $table }}/edit/{{ $row[$pk] }}" class="btn btn-warning btn-xs">Edit</a>
              <form method="POST" action="/dbmanager/table/{{ $table }}/delete/{{ $row[$pk] }}" style="display:inline">
                @csrf @method('DELETE')
                <button class="btn btn-danger btn-xs" onclick="return confirm('Delete row #{{ $row[$pk] }}?')">Del</button>
              </form>
            </div>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

@if($pages > 1)
<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:6px">
  <span class="text-muted text-sm">Page {{ $page }} of {{ $pages }} &middot; {{ number_format($total) }} rows</span>
  <div class="pagination">
    @if($page>1)
      <a href="?page=1&search={{ urlencode($search) }}&per_page={{ $perPage }}&order_by={{ $orderBy }}&order_dir={{ $orderDir }}" class="page-btn">&laquo;</a>
      <a href="?page={{ $page-1 }}&search={{ urlencode($search) }}&per_page={{ $perPage }}&order_by={{ $orderBy }}&order_dir={{ $orderDir }}" class="page-btn">&lsaquo;</a>
    @endif
    @for($p=max(1,$page-3);$p<=min($pages,$page+3);$p++)
      <a href="?page={{ $p }}&search={{ urlencode($search) }}&per_page={{ $perPage }}&order_by={{ $orderBy }}&order_dir={{ $orderDir }}" class="page-btn {{ $p==$page?'active':'' }}">{{ $p }}</a>
    @endfor
    @if($page<$pages)
      <a href="?page={{ $page+1 }}&search={{ urlencode($search) }}&per_page={{ $perPage }}&order_by={{ $orderBy }}&order_dir={{ $orderDir }}" class="page-btn">&rsaquo;</a>
      <a href="?page={{ $pages }}&search={{ urlencode($search) }}&per_page={{ $perPage }}&order_by={{ $orderBy }}&order_dir={{ $orderDir }}" class="page-btn">&raquo;</a>
    @endif
  </div>
</div>
@endif

@else
<div class="card">
  <div class="card-body" style="text-align:center;padding:56px 20px">
    <div style="width:52px;height:52px;background:#eff6ff;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#1d4ed8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/></svg>
    </div>
    <div style="font-size:15px;font-weight:700;color:#111827;margin-bottom:4px">No rows found</div>
    <div class="text-muted text-sm">{{ $search ? "No results for \"$search\"" : 'This table is empty.' }}</div>
    @if($search)
      <a href="/dbmanager/table/{{ $table }}" class="btn btn-secondary btn-sm" style="margin-top:12px">Clear search</a>
    @else
      <a href="/dbmanager/table/{{ $table }}/create" class="btn btn-success btn-sm" style="margin-top:12px">Insert first row</a>
    @endif
  </div>
</div>
@endif

{{-- Inline edit tooltip/save indicator --}}
<div id="save-indicator" style="display:none;position:fixed;bottom:20px;right:20px;background:#16a34a;color:#fff;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,.15);z-index:9999">
  &#10003; Saved
</div>
<div id="error-indicator" style="display:none;position:fixed;bottom:20px;right:20px;background:#dc2626;color:#fff;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,.15);z-index:9999">
  &#x2715; Error saving
</div>

@endsection

@section('scripts')
<script>
const TABLE   = '{{ $table }}';
const PK_COL  = '{{ $pk }}';
const CSRF    = document.querySelector('meta[name="csrf-token"]').content;
const COL_META = @json(collect($cols)->keyBy('name')->map(fn($c) => ['type' => $c['type'] ?? 'TEXT', 'pk' => $c['pk']])->toArray());

// ── Panel toggle ──────────────────────────────────────────────────────────────
function togglePanel(id) {
    const el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

// ── Bulk selection ────────────────────────────────────────────────────────────
function toggleAll(src) {
    document.querySelectorAll('.row-chk').forEach(c => c.checked = src.checked);
    updateBulkBar();
}
function clearSelection() {
    document.querySelectorAll('.row-chk').forEach(c => c.checked = false);
    document.getElementById('chk-all').checked = false;
    updateBulkBar();
}
function updateBulkBar() {
    const chks = [...document.querySelectorAll('.row-chk:checked')];
    const bar  = document.getElementById('bulk-bar');
    bar.style.display = chks.length ? 'flex' : 'none';
    document.getElementById('bulk-count').textContent = chks.length + ' row' + (chks.length > 1 ? 's' : '') + ' selected';
}
function getSelectedIds() {
    return [...document.querySelectorAll('.row-chk:checked')].map(c => c.value);
}

// ── Bulk delete ───────────────────────────────────────────────────────────────
function bulkDelete() {
    const ids = getSelectedIds();
    if (!ids.length) return;
    if (!confirm('Delete ' + ids.length + ' row(s)? This cannot be undone.')) return;
    const f = document.getElementById('bulk-delete-form');
    const c = document.getElementById('bulk-delete-ids');
    c.innerHTML = '';
    ids.forEach(id => {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
        c.appendChild(inp);
    });
    f.submit();
}

// ── Bulk edit page ────────────────────────────────────────────────────────────
function openBulkEditPage() {
    const ids = getSelectedIds();
    if (!ids.length) return;
    window.location.href = '/dbmanager/table/' + TABLE + '/bulk-edit-page?ids=' + ids.join(',');
}

// ── Inline cell editing ───────────────────────────────────────────────────────
let activeCell = null;

function getInputForType(rawType, currentVal) {
    const t = (rawType || '').toLowerCase();

    // ENUM — extract values
    if (t.startsWith('enum')) {
        const match = t.match(/enum\(([^)]+)\)/);
        const opts  = match ? match[1].split(',').map(v => v.replace(/'/g,'').trim()) : [];
        const sel   = document.createElement('select');
        sel.className = 'inline-input';
        const none = document.createElement('option');
        none.value = ''; none.textContent = '(NULL)';
        sel.appendChild(none);
        opts.forEach(o => {
            const opt = document.createElement('option');
            opt.value = o; opt.textContent = o;
            if (o === currentVal) opt.selected = true;
            sel.appendChild(opt);
        });
        return sel;
    }

    // TINYINT(1) / BOOLEAN
    if (t === 'tinyint(1)' || t === 'boolean' || t === 'bool') {
        const sel = document.createElement('select');
        sel.className = 'inline-input';
        [['', '(NULL)'], ['0', '0 (false)'], ['1', '1 (true)']].forEach(([v, l]) => {
            const opt = document.createElement('option');
            opt.value = v; opt.textContent = l;
            if (String(currentVal) === v) opt.selected = true;
            sel.appendChild(opt);
        });
        return sel;
    }

    const inp = document.createElement('input');
    inp.className = 'inline-input';

    if (t.includes('datetime') || t.includes('timestamp')) {
        inp.type = 'datetime-local';
        if (currentVal) {
            // Convert "2024-01-15 10:30:00" → "2024-01-15T10:30"
            inp.value = currentVal.replace(' ', 'T').substring(0, 16);
        }
    } else if (t.includes('date') && !t.includes('datetime')) {
        inp.type = 'date';
        inp.value = currentVal || '';
    } else if (t.includes('time') && !t.includes('datetime')) {
        inp.type = 'time';
        inp.value = currentVal || '';
    } else if (t.includes('int') || t.includes('float') || t.includes('double') || t.includes('decimal') || t.includes('numeric') || t.includes('real')) {
        inp.type = 'number';
        inp.value = currentVal ?? '';
        if (t.includes('decimal') || t.includes('float') || t.includes('double') || t.includes('real')) {
            inp.step = 'any';
        }
    } else if (t.includes('text') || t.includes('blob') || t.includes('json')) {
        const ta = document.createElement('textarea');
        ta.className = 'inline-input';
        ta.rows = 3;
        ta.value = currentVal ?? '';
        return ta;
    } else {
        inp.type = 'text';
        inp.value = currentVal ?? '';
    }
    return inp;
}

function startInlineEdit(cell) {
    if (activeCell && activeCell !== cell) cancelInlineEdit();

    const col      = cell.dataset.col;
    const pkVal    = cell.dataset.pkVal;
    const rawType  = cell.dataset.type;
    const curVal   = cell.dataset.val === 'null' ? null : cell.dataset.val;

    activeCell = cell;
    cell.classList.add('editing');

    const input = getInputForType(rawType, curVal);
    input.style.cssText = 'width:100%;min-width:120px;padding:4px 7px;border:2px solid #3b82f6;border-radius:5px;font-size:13px;background:#fff;color:#111827;box-shadow:0 0 0 3px rgba(59,130,246,.15)';

    // Save on Enter (not textarea), cancel on Escape
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter' && input.tagName !== 'TEXTAREA') { e.preventDefault(); saveInlineEdit(cell, input, col, pkVal); }
        if (e.key === 'Escape') cancelInlineEdit();
    });
    input.addEventListener('blur', () => {
        // Small delay so click on save button works
        setTimeout(() => { if (activeCell === cell) saveInlineEdit(cell, input, col, pkVal); }, 150);
    });

    cell.innerHTML = '';
    cell.appendChild(input);

    // Wrap with save/cancel buttons
    const btns = document.createElement('div');
    btns.style.cssText = 'display:flex;gap:4px;margin-top:4px';
    const saveBtn = document.createElement('button');
    saveBtn.textContent = '✓ Save';
    saveBtn.className = 'btn btn-success btn-xs';
    saveBtn.onmousedown = e => { e.preventDefault(); saveInlineEdit(cell, input, col, pkVal); };
    const cancelBtn = document.createElement('button');
    cancelBtn.textContent = '✕';
    cancelBtn.className = 'btn btn-secondary btn-xs';
    cancelBtn.onmousedown = e => { e.preventDefault(); cancelInlineEdit(); };
    btns.appendChild(saveBtn);
    btns.appendChild(cancelBtn);
    cell.appendChild(btns);

    input.focus();
    if (input.select) input.select();
}

function cancelInlineEdit() {
    if (!activeCell) return;
    const cell = activeCell;
    activeCell = null;
    cell.classList.remove('editing');
    const val = cell.dataset.val;
    cell.innerHTML = val === null || val === 'null' || val === ''
        ? '<span class="null-val">NULL</span>'
        : '<span class="cell-display">' + escHtml(val.length > 60 ? val.substring(0,60)+'…' : val) + '</span>';
}

function saveInlineEdit(cell, input, col, pkVal) {
    if (activeCell !== cell) return;
    let val = input.value;

    // Convert datetime-local back to MySQL format
    if (input.type === 'datetime-local' && val) {
        val = val.replace('T', ' ') + ':00';
    }

    activeCell = null;
    cell.classList.remove('editing');

    fetch('/dbmanager/table/' + TABLE + '/inline-update', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ pk_val: pkVal, col: col, val: val })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            cell.dataset.val = val;
            cell.innerHTML = val === '' || val === null
                ? '<span class="null-val">NULL</span>'
                : '<span class="cell-display">' + escHtml(val.length > 60 ? val.substring(0,60)+'…' : val) + '</span>';
            showIndicator('save-indicator');
        } else {
            cell.innerHTML = '<span style="color:#dc2626;font-size:12px">' + escHtml(data.error || 'Error') + '</span>';
            showIndicator('error-indicator');
        }
    })
    .catch(() => {
        cancelInlineEdit();
        showIndicator('error-indicator');
    });
}

function showIndicator(id) {
    const el = document.getElementById(id);
    el.style.display = 'block';
    setTimeout(() => el.style.display = 'none', 2000);
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Click outside to cancel
document.addEventListener('click', e => {
    if (activeCell && !activeCell.contains(e.target)) cancelInlineEdit();
});
</script>
<style>
.editable-cell:not(.pk-cell):hover { background: #eff6ff !important; }
.editable-cell.editing { background: #f0f9ff !important; padding: 6px 8px !important; vertical-align: top !important; max-width: none !important; overflow: visible !important; white-space: normal !important; }
.pk-cell { color: #6b7280; }
</style>
@endsection
