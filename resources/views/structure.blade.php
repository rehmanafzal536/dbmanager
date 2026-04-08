@extends('dbmanager::layout')
@section('content')
@php
$types   = ['INT','BIGINT','TINYINT','SMALLINT','FLOAT','DOUBLE','DECIMAL(10,2)','VARCHAR(255)','CHAR(50)','TEXT','MEDIUMTEXT','LONGTEXT','DATE','DATETIME','TIMESTAMP','TIME','YEAR','BLOB','LONGBLOB','BOOLEAN','JSON','ENUM'];
$actions = ['RESTRICT','CASCADE','SET NULL','NO ACTION'];
$colNames = array_column($cols, 'name');
@endphp

{{-- Header --}}
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:800;color:#111827">Structure</h1>
    <p style="font-size:13px;color:#6b7280;margin-top:2px">
      <a href="/dbmanager/table/{{ $table }}" style="color:#1d4ed8;text-decoration:none">{{ $table }}</a>
      &rsaquo; {{ count($cols) }} columns
    </p>
  </div>
  <div style="display:flex;gap:8px">
    <a href="/dbmanager/table/{{ $table }}" class="btn btn-secondary btn-sm">&#8592; Browse Data</a>
    <a href="/dbmanager/table/{{ $table }}/create" class="btn btn-success btn-sm">+ Insert Row</a>
  </div>
</div>

{{-- ═══ COLUMNS TABLE ═══════════════════════════════════════════════ --}}
<div class="card">
  <div class="card-header">
    <span>Columns ({{ count($cols) }})</span>
    <div style="display:flex;gap:6px">
      <button id="bulk-drop-btn" class="btn btn-danger btn-xs" style="display:none" onclick="bulkDrop()">Drop Selected</button>
      <button class="btn btn-secondary btn-xs" onclick="toggleAllCols()">Select All</button>
    </div>
  </div>
  <form id="bulk-drop-form" method="POST" action="/dbmanager/table/{{ $table }}/bulk-drop-columns">
    @csrf @method('DELETE')
    <div class="table-wrap">
      <table class="dt">
        <thead>
          <tr>
            <th style="width:36px"><input type="checkbox" id="chk-all-cols" onchange="toggleAllCols(this)" style="cursor:pointer;width:15px;height:15px"></th>
            <th style="width:36px">#</th>
            <th>Name</th>
            <th>Type</th>
            <th>Length</th>
            <th>Default</th>
            <th>Not Null</th>
            <th>Key</th>
            <th style="width:200px">Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($cols as $col)
          @php
            preg_match('/^([A-Za-z]+)/', $col['type'] ?: 'TEXT', $tm);
            $baseType = strtoupper($tm[1] ?? 'TEXT');
            preg_match('/\(([^)]+)\)/', $col['type'] ?? '', $lm);
            $colLen = $lm[1] ?? '';
          @endphp
          <tr>
            <td>
              @if(empty($col['pk']))
                <input type="checkbox" name="columns[]" value="{{ $col['name'] }}" class="col-chk" onchange="updateBulkDropBtn()" style="cursor:pointer;width:15px;height:15px">
              @endif
            </td>
            <td class="text-muted text-sm">{{ $col['cid'] }}</td>
            <td style="font-weight:600;color:#111827">{{ $col['name'] }}</td>
            <td><span class="badge badge-type">{{ $baseType }}</span></td>
            <td class="text-muted text-sm">{{ $colLen ?: '—' }}</td>
            <td class="text-muted text-sm">{{ $col['dflt_value'] ?? 'NULL' }}</td>
            <td>
              @if($col['notnull'])<span class="badge badge-yes">YES</span>
              @else<span class="badge badge-no">NO</span>@endif
            </td>
            <td>
              @if($col['pk'])<span class="badge badge-pk">PK</span>@endif
            </td>
            <td>
              <div style="display:flex;gap:4px;flex-wrap:wrap">
                <button type="button" class="btn btn-secondary btn-xs"
                  onclick="openEditModal('{{ $col['name'] }}','{{ addslashes($col['type'] ?: 'TEXT') }}','{{ addslashes($col['dflt_value'] ?? '') }}',{{ $col['notnull'] ? 1 : 0 }})">
                  Edit
                </button>
                <button type="button" class="btn btn-secondary btn-xs"
                  onclick="openRenameModal('{{ $col['name'] }}')">
                  Rename
                </button>
                @if(empty($col['pk']))
                  <button type="button" class="btn btn-danger btn-xs"
                    onclick="dropSingleCol('{{ $col['name'] }}')">
                    Drop
                  </button>
                @endif
              </div>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </form>
</div>

{{-- ═══ ADD COLUMNS (phpMyAdmin style) ══════════════════════════════ --}}
<div class="card">
  <div class="card-header">
    <span>Add Columns</span>
    <div style="display:flex;align-items:center;gap:8px">
      <label style="font-size:13px;color:#374151;font-weight:500">Count:</label>
      <input type="number" id="num-cols" value="1" min="1" max="20"
        style="width:60px;padding:5px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:13px">
      <button class="btn btn-secondary btn-sm" onclick="buildAddForm()">Go</button>
    </div>
  </div>
  <div style="overflow-x:auto">
    <form method="POST" action="/dbmanager/table/{{ $table }}/add-column" id="add-cols-form">
      @csrf
      <table class="dt" id="add-cols-table">
        <thead>
          <tr>
            <th>Name *</th>
            <th style="width:150px">Type</th>
            <th style="width:90px">Length</th>
            <th style="width:140px">Default</th>
            <th style="width:75px;text-align:center">Not Null</th>
            <th style="width:65px;text-align:center">Unique</th>
            <th style="width:75px;text-align:center">Auto Inc</th>
            <th style="width:140px">Position</th>
            <th style="width:32px"></th>
          </tr>
        </thead>
        <tbody id="add-cols-body">
          <tr>
            <td><input type="text" name="cols[0][name]" class="form-control" placeholder="column_name" required></td>
            <td>
              <select name="cols[0][type]" class="form-select">
                @foreach($types as $t)<option>{{ $t }}</option>@endforeach
              </select>
            </td>
            <td><input type="text" name="cols[0][length]" class="form-control" placeholder="255"></td>
            <td>
              <select name="cols[0][default_type]" class="form-select" onchange="toggleDef(this,0)">
                <option value="none">None</option>
                <option value="null">NULL</option>
                <option value="custom">Custom</option>
                <option value="current_timestamp">CURRENT_TIMESTAMP</option>
              </select>
              <input type="text" name="cols[0][default_val]" id="def-0" class="form-control" placeholder="value" style="margin-top:4px;display:none">
            </td>
            <td style="text-align:center"><input type="checkbox" name="cols[0][notnull]" style="width:16px;height:16px;cursor:pointer"></td>
            <td style="text-align:center"><input type="checkbox" name="cols[0][unique]" style="width:16px;height:16px;cursor:pointer"></td>
            <td style="text-align:center"><input type="checkbox" name="cols[0][auto_inc]" style="width:16px;height:16px;cursor:pointer"></td>
            <td>
              <select name="cols[0][position]" class="form-select">
                <option value="last">At End</option>
                <option value="first">At Beginning</option>
                @foreach($cols as $c)<option value="after_{{ $c['name'] }}">After {{ $c['name'] }}</option>@endforeach
              </select>
            </td>
            <td></td>
          </tr>
        </tbody>
      </table>
      <div style="padding:12px 16px;border-top:1px solid #e5e7eb;display:flex;gap:8px">
        <button type="submit" class="btn btn-primary btn-sm">Save Columns</button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="buildAddForm()">Reset</button>
      </div>
    </form>
  </div>
</div>

{{-- ═══ INDEXES ════════════════════════════════════════════════════ --}}
<div class="card">
  <div class="card-header">
    <span>Indexes ({{ count($indexes) }})</span>
    <button class="btn btn-secondary btn-sm" onclick="togglePanel('add-index-panel')">+ Add Index</button>
  </div>
  @if(count($indexes) > 0)
  <div class="table-wrap">
    <table class="dt">
      <thead>
        <tr><th>Name</th><th>Columns</th><th>Type</th><th>Unique</th><th style="width:80px">Action</th></tr>
      </thead>
      <tbody>
        @foreach($indexes as $idx)
        @php
          $idxName  = $idx['name'] ?? $idx['Key_name'] ?? '—';
          $isUnique = isset($idx['unique']) ? (bool)$idx['unique'] : (isset($idx['Non_unique']) ? !(bool)$idx['Non_unique'] : false);
          $idxCols  = isset($idx['columns']) && is_array($idx['columns']) ? implode(', ', $idx['columns']) : '—';
          $idxType  = $idx['origin'] ?? $idx['Index_type'] ?? 'BTREE';
        @endphp
        <tr>
          <td style="font-weight:600">{{ $idxName }}</td>
          <td><code style="background:#f3f4f6;padding:2px 7px;border-radius:4px;font-size:12px">{{ $idxCols }}</code></td>
          <td><span class="badge badge-type">{{ $idxType }}</span></td>
          <td>
            @if($isUnique)<span class="badge badge-yes">UNIQUE</span>
            @else<span class="badge badge-no">NO</span>@endif
          </td>
          <td>
            <form method="POST" action="/dbmanager/table/{{ $table }}/drop-index" style="display:inline">
              @csrf @method('DELETE')
              <input type="hidden" name="index_name" value="{{ $idxName }}">
              <button class="btn btn-danger btn-xs" onclick="return confirm('Drop index {{ $idxName }}?')">Drop</button>
            </form>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @else
  <div class="card-body text-muted text-sm">No indexes defined.</div>
  @endif

  <div id="add-index-panel" style="display:none;border-top:1px solid #e5e7eb;padding:16px">
    <form method="POST" action="/dbmanager/table/{{ $table }}/add-index">
      @csrf
      <div style="display:grid;grid-template-columns:1fr 130px 180px auto;gap:12px;align-items:flex-end">
        <div>
          <label class="form-label">Index Name</label>
          <input type="text" name="index_name" class="form-control" placeholder="idx_col_name">
        </div>
        <div>
          <label class="form-label">Type</label>
          <select name="index_type" class="form-select">
            <option value="INDEX">INDEX</option>
            <option value="UNIQUE">UNIQUE</option>
            @if(!$isSqlite)<option value="FULLTEXT">FULLTEXT</option>@endif
          </select>
        </div>
        <div>
          <label class="form-label">Columns (hold Ctrl for multi)</label>
          <select name="index_cols[]" class="form-select" multiple style="height:80px">
            @foreach($cols as $c)<option value="{{ $c['name'] }}">{{ $c['name'] }}</option>@endforeach
          </select>
        </div>
        <div>
          <button class="btn btn-primary btn-sm">Create</button>
        </div>
      </div>
    </form>
  </div>
</div>

{{-- ═══ FOREIGN KEYS (MySQL only) ═════════════════════════════════ --}}
@if(!$isSqlite)
<div class="card">
  <div class="card-header">
    <span>Foreign Keys ({{ count($foreignKeys) }})</span>
    <button class="btn btn-secondary btn-sm" onclick="togglePanel('add-fk-panel')">+ Add Foreign Key</button>
  </div>
  @if(count($foreignKeys) > 0)
  <div class="table-wrap">
    <table class="dt">
      <thead>
        <tr><th>Constraint</th><th>Column</th><th>References</th><th>On Delete</th><th>On Update</th><th style="width:80px">Action</th></tr>
      </thead>
      <tbody>
        @foreach($foreignKeys as $fk)
        <tr>
          <td style="font-weight:600;font-size:12px">{{ $fk->name }}</td>
          <td><code style="background:#f3f4f6;padding:2px 7px;border-radius:4px;font-size:12px">{{ $fk->col }}</code></td>
          <td>
            <a href="/dbmanager/table/{{ $fk->ref_table }}/structure" style="color:#1d4ed8;text-decoration:none;font-size:13px">
              {{ $fk->ref_table }}.{{ $fk->ref_col }}
            </a>
          </td>
          <td><span class="badge badge-type">{{ $fk->on_delete }}</span></td>
          <td><span class="badge badge-type">{{ $fk->on_update }}</span></td>
          <td>
            <form method="POST" action="/dbmanager/table/{{ $table }}/drop-foreign-key" style="display:inline">
              @csrf @method('DELETE')
              <input type="hidden" name="fk_name" value="{{ $fk->name }}">
              <button class="btn btn-danger btn-xs" onclick="return confirm('Drop FK {{ $fk->name }}?')">Drop</button>
            </form>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @else
  <div class="card-body text-muted text-sm">No foreign keys defined.</div>
  @endif

  <div id="add-fk-panel" style="display:none;border-top:1px solid #e5e7eb;padding:16px">
    <form method="POST" action="/dbmanager/table/{{ $table }}/add-foreign-key">
      @csrf
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr 110px 110px auto;gap:12px;align-items:flex-end">
        <div>
          <label class="form-label">Column</label>
          <select name="fk_col" class="form-select">
            @foreach($cols as $c)
              @if(empty($c['pk']))<option>{{ $c['name'] }}</option>@endif
            @endforeach
          </select>
        </div>
        <div>
          <label class="form-label">References Table</label>
          <select name="fk_ref_table" class="form-select">
            @foreach($allTables as $t)<option>{{ $t }}</option>@endforeach
          </select>
        </div>
        <div>
          <label class="form-label">References Column</label>
          <input type="text" name="fk_ref_col" class="form-control" value="id">
        </div>
        <div>
          <label class="form-label">On Delete</label>
          <select name="fk_on_delete" class="form-select">
            @foreach($actions as $a)<option>{{ $a }}</option>@endforeach
          </select>
        </div>
        <div>
          <label class="form-label">On Update</label>
          <select name="fk_on_update" class="form-select">
            @foreach($actions as $a)<option>{{ $a }}</option>@endforeach
          </select>
        </div>
        <div><button class="btn btn-primary btn-sm">Add FK</button></div>
      </div>
    </form>
  </div>
</div>
@endif

{{-- ═══ EDIT COLUMN MODAL ══════════════════════════════════════════ --}}
<div id="edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;width:540px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.2)">
    <div style="padding:16px 20px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between">
      <span style="font-size:15px;font-weight:700;color:#111827">Edit Column</span>
      <button onclick="closeModal('edit-modal')" style="background:none;border:none;cursor:pointer;font-size:22px;color:#6b7280;line-height:1">&times;</button>
    </div>
    <form method="POST" id="edit-col-form" action="">
      @csrf
      <input type="hidden" name="col_name" id="edit-col-name">
      <div style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div>
          <label class="form-label">New Name</label>
          <input type="text" name="col_newname" id="edit-col-newname" class="form-control">
        </div>
        <div>
          <label class="form-label">Type</label>
          <select name="col_type" id="edit-col-type" class="form-select">
            @foreach($types as $t)<option>{{ $t }}</option>@endforeach
          </select>
        </div>
        <div>
          <label class="form-label">Default Value</label>
          <input type="text" name="col_default" id="edit-col-default" class="form-control" placeholder="NULL">
        </div>
        <div style="display:flex;align-items:center;gap:8px;padding-top:22px">
          <input type="checkbox" name="col_notnull" id="edit-col-notnull" style="width:16px;height:16px;cursor:pointer">
          <label for="edit-col-notnull" style="cursor:pointer;font-size:13.5px;color:#374151;font-weight:500">Not Null</label>
        </div>
      </div>
      <div style="padding:14px 20px;border-top:1px solid #e5e7eb;display:flex;gap:8px;justify-content:flex-end">
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('edit-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
      </div>
    </form>
  </div>
</div>

{{-- ═══ RENAME COLUMN MODAL ════════════════════════════════════════ --}}
<div id="rename-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;width:400px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.2)">
    <div style="padding:16px 20px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between">
      <span style="font-size:15px;font-weight:700;color:#111827">Rename Column</span>
      <button onclick="closeModal('rename-modal')" style="background:none;border:none;cursor:pointer;font-size:22px;color:#6b7280;line-height:1">&times;</button>
    </div>
    <form method="POST" action="/dbmanager/table/{{ $table }}/rename-column">
      @csrf
      <div style="padding:20px;display:flex;flex-direction:column;gap:14px">
        <div>
          <label class="form-label">Current Name</label>
          <input type="text" id="rename-old-display" class="form-control" disabled style="background:#f9fafb;color:#6b7280">
        </div>
        <div>
          <label class="form-label">New Name *</label>
          <input type="text" name="new_name" id="rename-new" class="form-control" required>
          <input type="hidden" name="old_name" id="rename-old-hidden">
        </div>
      </div>
      <div style="padding:14px 20px;border-top:1px solid #e5e7eb;display:flex;gap:8px;justify-content:flex-end">
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('rename-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Rename</button>
      </div>
    </form>
  </div>
</div>

{{-- Hidden drop single col form --}}
<form id="drop-single-form" method="POST" action="" style="display:none">
  @csrf @method('DELETE')
  <input type="hidden" name="col_name" id="drop-single-name">
</form>

@endsection
@section('scripts')
<script>
const TABLE    = '{{ $table }}';
const TYPES    = @json($types);
const POS_COLS = @json($colNames);

function togglePanel(id) {
    const el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
function openModal(id)  { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

// Bulk drop columns
function toggleAllCols(src) {
    const chks  = document.querySelectorAll('.col-chk');
    const state = src ? src.checked : ![...chks].some(c => c.checked);
    chks.forEach(c => c.checked = state);
    updateBulkDropBtn();
}
function updateBulkDropBtn() {
    const any = document.querySelectorAll('.col-chk:checked').length > 0;
    document.getElementById('bulk-drop-btn').style.display = any ? 'inline-flex' : 'none';
}
function bulkDrop() {
    const names = [...document.querySelectorAll('.col-chk:checked')].map(c => c.value);
    if (!confirm('Drop columns: ' + names.join(', ') + '? Cannot be undone.')) return;
    document.getElementById('bulk-drop-form').submit();
}

// Drop single column
function dropSingleCol(name) {
    if (!confirm('Drop column "' + name + '"? Cannot be undone.')) return;
    const f = document.getElementById('drop-single-form');
    f.action = '/dbmanager/table/' + TABLE + '/drop-column';
    document.getElementById('drop-single-name').value = name;
    f.submit();
}

// Edit column modal
function openEditModal(name, type, dflt, notnull) {
    document.getElementById('edit-col-name').value     = name;
    document.getElementById('edit-col-newname').value  = name;
    document.getElementById('edit-col-default').value  = dflt;
    document.getElementById('edit-col-notnull').checked = !!notnull;
    const sel  = document.getElementById('edit-col-type');
    const base = type.split('(')[0].toUpperCase();
    [...sel.options].forEach(o => { o.selected = o.value.toUpperCase().startsWith(base); });
    document.getElementById('edit-col-form').action = '/dbmanager/table/' + TABLE + '/modify-column';
    openModal('edit-modal');
}

// Rename modal
function openRenameModal(name) {
    document.getElementById('rename-old-display').value = name;
    document.getElementById('rename-old-hidden').value  = name;
    document.getElementById('rename-new').value         = name;
    openModal('rename-modal');
}

// Build add-columns form
function buildAddForm() {
    const n    = Math.max(1, Math.min(20, parseInt(document.getElementById('num-cols').value) || 1));
    const body = document.getElementById('add-cols-body');
    const opts = TYPES.map(t => '<option>' + t + '</option>').join('');
    const posOpts = '<option value="last">At End</option><option value="first">At Beginning</option>'
        + POS_COLS.map(c => '<option value="after_' + c + '">After ' + c + '</option>').join('');
    body.innerHTML = '';
    for (let i = 0; i < n; i++) {
        const rmBtn = n > 1 ? '<button type="button" class="btn btn-danger btn-xs" onclick="this.closest(\'tr\').remove()">&#x2715;</button>' : '';
        body.insertAdjacentHTML('beforeend',
            '<tr>' +
            '<td><input type="text" name="cols[' + i + '][name]" class="form-control" placeholder="column_name" required></td>' +
            '<td><select name="cols[' + i + '][type]" class="form-select">' + opts + '</select></td>' +
            '<td><input type="text" name="cols[' + i + '][length]" class="form-control" placeholder="255"></td>' +
            '<td>' +
              '<select name="cols[' + i + '][default_type]" class="form-select" onchange="toggleDef(this,' + i + ')">' +
                '<option value="none">None</option><option value="null">NULL</option>' +
                '<option value="custom">Custom</option><option value="current_timestamp">CURRENT_TIMESTAMP</option>' +
              '</select>' +
              '<input type="text" name="cols[' + i + '][default_val]" id="def-' + i + '" class="form-control" placeholder="value" style="margin-top:4px;display:none">' +
            '</td>' +
            '<td style="text-align:center"><input type="checkbox" name="cols[' + i + '][notnull]" style="width:16px;height:16px;cursor:pointer"></td>' +
            '<td style="text-align:center"><input type="checkbox" name="cols[' + i + '][unique]" style="width:16px;height:16px;cursor:pointer"></td>' +
            '<td style="text-align:center"><input type="checkbox" name="cols[' + i + '][auto_inc]" style="width:16px;height:16px;cursor:pointer"></td>' +
            '<td><select name="cols[' + i + '][position]" class="form-select">' + posOpts + '</select></td>' +
            '<td>' + rmBtn + '</td>' +
            '</tr>'
        );
    }
}

function toggleDef(sel, idx) {
    const inp = document.getElementById('def-' + idx);
    if (inp) inp.style.display = sel.value === 'custom' ? 'block' : 'none';
}

// Close modals on backdrop click
['edit-modal','rename-modal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) closeModal(id);
    });
});
</script>
@endsection
