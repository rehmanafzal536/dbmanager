@extends('dbmanager::layout')
@section('content')

<div style="margin-bottom:20px">
  <h1 style="font-size:24px;font-weight:700;color:#fff;text-shadow:0 2px 8px rgba(0,0,0,0.2)">
    🔄 Import & Convert Database
  </h1>
  <p style="color:rgba(255,255,255,0.75);margin-top:6px;font-size:14px">
    Auto-detects source format and converts to your current database
    (<strong style="color:#fff">{{ strtoupper($currentDriver) }}</strong>)
  </p>
</div>

@if(session('import_log'))
<div class="card" style="margin-bottom:20px">
  <div class="card-header" style="background:linear-gradient(135deg,#48bb78 0%,#38a169 100%)">
    ✅ Import Results
  </div>
  <div class="card-body">
    @foreach(session('import_log') as $line)
    <div style="padding:8px 12px;margin-bottom:6px;background:#f0fff4;border:1px solid #c6f6d5;border-radius:8px;font-size:13px;color:#22543d;font-family:monospace">
      {{ $line }}
    </div>
    @endforeach
  </div>
</div>
@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">

  {{-- What this does --}}
  <div class="card">
    <div class="card-header">📖 How It Works</div>
    <div class="card-body">
      <div style="display:flex;flex-direction:column;gap:14px">
        <div style="display:flex;gap:12px;align-items:flex-start">
          <span style="font-size:24px">🗃️</span>
          <div>
            <div style="font-weight:700;color:#2d3748;font-size:14px">SQLite File (.sqlite)</div>
            <div style="color:#718096;font-size:13px;margin-top:3px">
              Upload a <code style="background:#edf2f7;padding:2px 6px;border-radius:4px">.sqlite</code> file.
              All tables and rows are read directly and imported into your current
              <strong>{{ strtoupper($currentDriver) }}</strong> database.
              Types are auto-converted.
            </div>
          </div>
        </div>
        <div style="display:flex;gap:12px;align-items:flex-start">
          <span style="font-size:24px">📄</span>
          <div>
            <div style="font-weight:700;color:#2d3748;font-size:14px">SQL Dump (.sql)</div>
            <div style="color:#718096;font-size:13px;margin-top:3px">
              Upload a MySQL or SQLite <code style="background:#edf2f7;padding:2px 6px;border-radius:4px">.sql</code> dump.
              The syntax is auto-detected and converted to match your current
              <strong>{{ strtoupper($currentDriver) }}</strong> database.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Conversion matrix --}}
  <div class="card">
    <div class="card-header">🔀 Conversion Matrix</div>
    <div class="card-body" style="padding:0">
      <table class="dtable">
        <thead>
          <tr>
            <th>Upload</th>
            <th>Current DB</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><span style="background:#bee3f8;color:#2c5282;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:600">.sqlite file</span></td>
            <td><span style="background:#c6f6d5;color:#22543d;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:600">MySQL</span></td>
            <td style="color:#4a5568;font-size:12px">SQLite → MySQL (types converted)</td>
          </tr>
          <tr>
            <td><span style="background:#bee3f8;color:#2c5282;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:600">.sqlite file</span></td>
            <td><span style="background:#bee3f8;color:#2c5282;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:600">SQLite</span></td>
            <td style="color:#4a5568;font-size:12px">Direct import (same format)</td>
          </tr>
          <tr>
            <td><span style="background:#feebc8;color:#7c2d12;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:600">MySQL .sql</span></td>
            <td><span style="background:#bee3f8;color:#2c5282;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:600">SQLite</span></td>
            <td style="color:#4a5568;font-size:12px">MySQL → SQLite (syntax converted)</td>
          </tr>
          <tr>
            <td><span style="background:#feebc8;color:#7c2d12;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:600">MySQL .sql</span></td>
            <td><span style="background:#c6f6d5;color:#22543d;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:600">MySQL</span></td>
            <td style="color:#4a5568;font-size:12px">Direct import (same format)</td>
          </tr>
          <tr>
            <td><span style="background:#e9d8fd;color:#44337a;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:600">SQLite .sql</span></td>
            <td><span style="background:#c6f6d5;color:#22543d;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:600">MySQL</span></td>
            <td style="color:#4a5568;font-size:12px">SQLite SQL → MySQL (syntax converted)</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

</div>

{{-- Upload form --}}
<div class="card">
  <div class="card-header">⬆ Upload & Import</div>
  <div class="card-body">
    <form method="POST" action="/dbmanager/import/convert" enctype="multipart/form-data" id="import-form">
      @csrf

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
        <div>
          <label class="form-label">Database File</label>
          <div id="drop-zone" style="border:2px dashed #cbd5e0;border-radius:12px;padding:32px;text-align:center;cursor:pointer;transition:all 0.2s;background:#f7fafc" onclick="document.getElementById('db_file').click()">
            <div style="font-size:40px;margin-bottom:10px">📂</div>
            <div style="font-weight:600;color:#4a5568;font-size:14px">Click or drag & drop</div>
            <div style="color:#a0aec0;font-size:12px;margin-top:4px">Supports .sqlite, .db, .sql files</div>
            <div id="file-name" style="margin-top:10px;color:#667eea;font-weight:600;font-size:13px;display:none"></div>
          </div>
          <input type="file" name="db_file" id="db_file" accept=".sqlite,.db,.sql" style="display:none" required>
        </div>

        <div>
          <label class="form-label">Import Mode</label>
          <div style="display:flex;flex-direction:column;gap:12px;margin-top:8px">
            <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;padding:14px;border:2px solid #e2e8f0;border-radius:10px;transition:all 0.2s" id="mode-merge-label">
              <input type="radio" name="mode" value="merge" checked style="margin-top:2px;width:16px;height:16px;cursor:pointer">
              <div>
                <div style="font-weight:700;color:#2d3748;font-size:13px">Merge (Skip Duplicates)</div>
                <div style="color:#718096;font-size:12px;margin-top:2px">Insert new rows, skip rows that already exist (by primary key)</div>
              </div>
            </label>
            <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;padding:14px;border:2px solid #e2e8f0;border-radius:10px;transition:all 0.2s" id="mode-replace-label">
              <input type="radio" name="mode" value="replace" style="margin-top:2px;width:16px;height:16px;cursor:pointer">
              <div>
                <div style="font-weight:700;color:#e53e3e;font-size:13px">⚠️ Replace All (Wipe & Reimport)</div>
                <div style="color:#718096;font-size:12px;margin-top:2px">Drops all existing tables then imports everything fresh. All current data will be lost.</div>
              </div>
            </label>
          </div>

          <div style="margin-top:16px;padding:14px;background:#fffbeb;border:2px solid #f6e05e;border-radius:10px">
            <div style="font-weight:700;color:#744210;font-size:13px;margin-bottom:4px">⚠️ Important</div>
            <div style="color:#92400e;font-size:12px;line-height:1.6">
              This operation modifies your database. Take a
              <a href="/dbmanager/backup" style="color:#667eea;font-weight:600">backup</a>
              before importing large datasets.
            </div>
          </div>
        </div>
      </div>

      <div style="border-top:2px solid #e2e8f0;padding-top:20px;display:flex;gap:12px;align-items:center">
        <button type="submit" class="btn btn-primary" id="submit-btn">
          🔄 Import & Convert
        </button>
        <a href="/dbmanager" class="btn btn-secondary">Cancel</a>
        <span id="loading" style="display:none;color:#667eea;font-size:13px;font-weight:600">
          ⏳ Processing... this may take a moment for large databases
        </span>
      </div>
    </form>
  </div>
</div>

@endsection
@section('scripts')
<script>
// File drag & drop
const dropZone = document.getElementById('drop-zone');
const fileInput = document.getElementById('db_file');
const fileName  = document.getElementById('file-name');

fileInput.addEventListener('change', () => {
  if (fileInput.files[0]) {
    fileName.textContent = '📄 ' + fileInput.files[0].name;
    fileName.style.display = 'block';
    dropZone.style.borderColor = '#667eea';
    dropZone.style.background = '#ebf4ff';
  }
});

dropZone.addEventListener('dragover', e => {
  e.preventDefault();
  dropZone.style.borderColor = '#667eea';
  dropZone.style.background = '#ebf4ff';
});
dropZone.addEventListener('dragleave', () => {
  dropZone.style.borderColor = '#cbd5e0';
  dropZone.style.background = '#f7fafc';
});
dropZone.addEventListener('drop', e => {
  e.preventDefault();
  const file = e.dataTransfer.files[0];
  if (file) {
    const dt = new DataTransfer();
    dt.items.add(file);
    fileInput.files = dt.files;
    fileName.textContent = '📄 ' + file.name;
    fileName.style.display = 'block';
    dropZone.style.borderColor = '#667eea';
    dropZone.style.background = '#ebf4ff';
  }
});

// Radio highlight
document.querySelectorAll('input[name="mode"]').forEach(radio => {
  radio.addEventListener('change', () => {
    document.getElementById('mode-merge-label').style.borderColor = '#e2e8f0';
    document.getElementById('mode-replace-label').style.borderColor = '#e2e8f0';
    document.getElementById('mode-' + radio.value + '-label').style.borderColor = '#667eea';
  });
});
document.getElementById('mode-merge-label').style.borderColor = '#667eea';

// Loading state
document.getElementById('import-form').addEventListener('submit', () => {
  document.getElementById('submit-btn').disabled = true;
  document.getElementById('loading').style.display = 'inline';
});
</script>
@endsection
