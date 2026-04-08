<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>DB Manager</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;font-size:14px;background:#f0f2f5;color:#1a1d23;display:flex;min-height:100vh;line-height:1.5;overflow:hidden;height:100vh}

/* ═══ Sidebar ═══════════════════════════════════════════════════════ */
.sidebar{width:250px;min-width:250px;background:#fff;border-right:1px solid #e5e7eb;display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;overflow-y:auto;z-index:20;box-shadow:2px 0 8px rgba(0,0,0,.04)}

.sidebar-brand{padding:16px 18px;display:flex;align-items:center;gap:12px;border-bottom:1px solid #e5e7eb;text-decoration:none}
.sidebar-brand-icon{width:38px;height:38px;background:linear-gradient(135deg,#1d4ed8,#3b82f6);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 2px 8px rgba(29,78,216,.3)}
.sidebar-brand-icon svg{color:#fff}
.sidebar-brand-name{font-size:15px;font-weight:800;color:#111827;letter-spacing:-.3px}
.sidebar-brand-sub{font-size:11px;color:#6b7280;margin-top:1px}

.sidebar-nav{padding:10px 0;flex:1}
.sidebar-section{margin-bottom:6px}
.sidebar-label{padding:8px 18px 4px;font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:1px}

.sidebar-link{display:flex;align-items:center;gap:10px;padding:9px 18px;color:#374151;font-size:13.5px;font-weight:500;text-decoration:none;border-left:3px solid transparent;transition:all .12s;margin:1px 0}
.sidebar-link .s-icon{width:20px;height:20px;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#9ca3af;transition:color .12s}
.sidebar-link:hover{background:#f9fafb;color:#111827}
.sidebar-link:hover .s-icon{color:#374151}
.sidebar-link.active{background:linear-gradient(90deg,#eff6ff,#f0f9ff);color:#1d4ed8;border-left-color:#1d4ed8;font-weight:600}
.sidebar-link.active .s-icon{color:#1d4ed8}
.sidebar-link .ltext{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sidebar-link .cnt{background:#f3f4f6;color:#6b7280;font-size:10.5px;padding:2px 7px;border-radius:12px;font-weight:700;flex-shrink:0}
.sidebar-link.active .cnt{background:#dbeafe;color:#1d4ed8}

.sidebar-footer{border-top:1px solid #e5e7eb;padding:8px 0}

/* ═══ Main ══════════════════════════════════════════════════════════ */
.main{margin-left:250px;flex:1;display:flex;flex-direction:column;height:100vh;overflow:hidden}

.topbar{background:#fff;border-bottom:1px solid #e5e7eb;padding:12px 24px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;flex-shrink:0;z-index:10;box-shadow:0 1px 4px rgba(0,0,0,.04)}
.topbar-left{display:flex;align-items:center;gap:10px}
.topbar-title{font-size:17px;font-weight:700;color:#111827}
.topbar-sub{font-size:12.5px;color:#6b7280;background:#f3f4f6;padding:3px 10px;border-radius:20px;font-weight:500}
.topbar-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}

.content{padding:24px;flex:1;overflow-y:auto;overflow-x:hidden}

/* ═══ Alerts ════════════════════════════════════════════════════════ */
.alert{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13.5px;display:flex;align-items:center;gap:10px;border:1px solid transparent;font-weight:500}
.alert svg{flex-shrink:0}
.alert-success{background:#f0fdf4;border-color:#bbf7d0;color:#166534}
.alert-danger{background:#fef2f2;border-color:#fecaca;color:#991b1b}
.alert-info{background:#eff6ff;border-color:#bfdbfe;color:#1e40af}
.alert-warning{background:#fffbeb;border-color:#fde68a;color:#92400e}

/* ═══ Cards ═════════════════════════════════════════════════════════ */
.card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;margin-bottom:20px;box-shadow:0 1px 4px rgba(0,0,0,.04)}
.card-header{background:#f9fafb;border-bottom:1px solid #e5e7eb;padding:12px 18px;font-weight:600;font-size:14px;color:#111827;display:flex;align-items:center;justify-content:space-between;gap:10px}
.card-body{padding:18px}

/* ═══ Data Table ════════════════════════════════════════════════════ */
.table-wrap{overflow-x:auto}
table.dt{width:100%;border-collapse:collapse;font-size:13.5px}
table.dt th{background:#f9fafb;color:#374151;padding:11px 14px;text-align:left;font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid #e5e7eb;white-space:nowrap}
table.dt th a{color:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:4px}
table.dt th a:hover{color:#1d4ed8}
table.dt td{padding:11px 14px;border-bottom:1px solid #f3f4f6;color:#1a1d23;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:middle}
table.dt tr:last-child td{border-bottom:none}
table.dt tr:hover td{background:#f9fafb}
.null-val{color:#d1d5db;font-style:italic;font-size:12px}

/* ═══ Buttons ═══════════════════════════════════════════════════════ */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:7px;border:1px solid transparent;cursor:pointer;font-size:13.5px;font-weight:500;text-decoration:none;transition:all .12s;white-space:nowrap;line-height:1.4}
.btn svg{flex-shrink:0}
.btn-primary{background:#1d4ed8;border-color:#1d4ed8;color:#fff}
.btn-primary:hover{background:#1e40af;color:#fff}
.btn-success{background:#16a34a;border-color:#16a34a;color:#fff}
.btn-success:hover{background:#15803d;color:#fff}
.btn-danger{background:#dc2626;border-color:#dc2626;color:#fff}
.btn-danger:hover{background:#b91c1c;color:#fff}
.btn-warning{background:#d97706;border-color:#d97706;color:#fff}
.btn-warning:hover{background:#b45309;color:#fff}
.btn-secondary{background:#fff;border-color:#d1d5db;color:#374151}
.btn-secondary:hover{background:#f9fafb;color:#111827;border-color:#9ca3af}
.btn-info{background:#0891b2;border-color:#0891b2;color:#fff}
.btn-info:hover{background:#0e7490;color:#fff}
.btn-sm{padding:6px 12px;font-size:13px}
.btn-xs{padding:4px 9px;font-size:12px}

/* ═══ Forms ═════════════════════════════════════════════════════════ */
.form-label{display:block;font-size:12.5px;font-weight:600;color:#374151;margin-bottom:5px}
.form-control,.form-select{width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:7px;font-size:13.5px;color:#111827;background:#fff;transition:border-color .15s,box-shadow .15s;line-height:1.4}
.form-control:focus,.form-select:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.15)}
.form-control::placeholder{color:#9ca3af}
.form-hint{font-size:12px;color:#6b7280;margin-top:4px}

/* ═══ Badges ════════════════════════════════════════════════════════ */
.badge{display:inline-block;padding:3px 8px;border-radius:5px;font-size:11.5px;font-weight:600}
.badge-type{background:#f3f4f6;color:#374151;border:1px solid #e5e7eb}
.badge-pk{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
.badge-yes{background:#f0fdf4;color:#166534}
.badge-no{background:#f3f4f6;color:#6b7280}

/* ═══ SQL Editor ════════════════════════════════════════════════════ */
.sql-editor{width:100%;min-height:180px;padding:14px;background:#1e1e2e;color:#cdd6f4;border:1px solid #45475a;border-radius:8px;font-family:'Fira Code','Cascadia Code','Consolas',monospace;font-size:13.5px;line-height:1.7;resize:vertical}
.sql-editor:focus{outline:none;border-color:#3b82f6}

/* ═══ Pagination ════════════════════════════════════════════════════ */
.pagination{display:flex;gap:5px;flex-wrap:wrap;align-items:center}
.page-btn{padding:6px 11px;border:1px solid #e5e7eb;border-radius:6px;background:#fff;color:#374151;font-size:13px;text-decoration:none;transition:all .12s;font-weight:500}
.page-btn:hover{background:#eff6ff;border-color:#93c5fd;color:#1d4ed8}
.page-btn.active{background:#1d4ed8;border-color:#1d4ed8;color:#fff}

/* ═══ Stats ═════════════════════════════════════════════════════════ */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.stat-box{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:18px 20px;display:flex;align-items:center;gap:14px;box-shadow:0 1px 4px rgba(0,0,0,.04)}
.stat-icon{width:46px;height:46px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.stat-icon.blue{background:#eff6ff;color:#1d4ed8}
.stat-icon.green{background:#f0fdf4;color:#16a34a}
.stat-icon.amber{background:#fffbeb;color:#d97706}
.stat-icon.cyan{background:#ecfeff;color:#0891b2}
.stat-val{font-size:24px;font-weight:800;color:#111827;line-height:1}
.stat-lbl{font-size:12px;color:#6b7280;margin-top:3px;font-weight:500}

/* ═══ Table Cards ═══════════════════════════════════════════════════ */
.tcard-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px}
.tcard{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px;text-decoration:none;color:inherit;display:flex;flex-direction:column;gap:4px;transition:all .15s;box-shadow:0 1px 4px rgba(0,0,0,.04)}
.tcard:hover{border-color:#93c5fd;box-shadow:0 4px 16px rgba(0,0,0,.08);transform:translateY(-1px)}
.tcard-icon{width:36px;height:36px;background:#eff6ff;border-radius:8px;display:flex;align-items:center;justify-content:center;margin-bottom:6px;color:#1d4ed8}
.tcard-name{font-size:14px;font-weight:700;color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.tcard-count{font-size:26px;font-weight:800;color:#1d4ed8;line-height:1.1;margin:4px 0 2px}
.tcard-label{font-size:12px;color:#6b7280;margin-bottom:10px}
.tcard-actions{display:flex;gap:6px;flex-wrap:wrap;margin-top:auto}

/* ═══ Misc ══════════════════════════════════════════════════════════ */
.row-actions{display:flex;gap:5px}
.text-muted{color:#6b7280}
.text-sm{font-size:12.5px}
.form-grid{display:grid;gap:16px}
.form-grid-2{grid-template-columns:1fr 1fr}
.form-grid-3{grid-template-columns:1fr 1fr 1fr}

::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-track{background:#f0f2f5}
::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:3px}
::-webkit-scrollbar-thumb:hover{background:#9ca3af}

@media(max-width:960px){
  .sidebar{position:relative;width:100%;min-width:unset;height:auto;border-right:none;border-bottom:1px solid #e5e7eb;box-shadow:none}
  .main{margin-left:0;height:auto;overflow:visible}
  .content{overflow:visible}
  .form-grid-2,.form-grid-3{grid-template-columns:1fr}
  .stats-grid{grid-template-columns:1fr 1fr}
}
</style>
</head>
<body>

<!-- ═══ Sidebar ═══════════════════════════════════════════════════════ -->
<nav class="sidebar">

  <a href="/dbmanager" class="sidebar-brand" style="text-decoration:none">
    <div class="sidebar-brand-icon">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4.03 3 9 3s9-1.34 9-3"/></svg>
    </div>
    <div>
      <div class="sidebar-brand-name">DB Manager</div>
      <div class="sidebar-brand-sub">{{ strtoupper(config('database.default')) }}</div>
    </div>
  </a>

  <div class="sidebar-nav">
    <div class="sidebar-section">
      <div class="sidebar-label">Navigation</div>

      <a href="/dbmanager" class="sidebar-link {{ request()->is('dbmanager') && !request()->is('dbmanager/*') ? 'active' : '' }}">
        <span class="s-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg></span>
        <span class="ltext">Overview</span>
      </a>

      <a href="/dbmanager/sql" class="sidebar-link {{ request()->is('dbmanager/sql') ? 'active' : '' }}">
        <span class="s-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg></span>
        <span class="ltext">SQL Query</span>
      </a>

      <a href="/dbmanager/create-table" class="sidebar-link {{ request()->is('dbmanager/create-table') ? 'active' : '' }}">
        <span class="s-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg></span>
        <span class="ltext">New Table</span>
      </a>

      <a href="/dbmanager/import" class="sidebar-link {{ request()->is('dbmanager/import') ? 'active' : '' }}">
        <span class="s-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg></span>
        <span class="ltext">Import / Convert</span>
      </a>
    </div>

    <div class="sidebar-section">
      <div class="sidebar-label">Tables ({{ count($tables ?? []) }})</div>
      @foreach($tables ?? [] as $t => $cnt)
      <a href="/dbmanager/table/{{ $t }}" class="sidebar-link {{ request()->segment(3)==$t ? 'active' : '' }}">
        <span class="s-icon"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/></svg></span>
        <span class="ltext">{{ $t }}</span>
        <span class="cnt">{{ number_format($cnt) }}</span>
      </a>
      @endforeach
    </div>
  </div>

  <div class="sidebar-footer">
    <a href="/dbmanager/settings" class="sidebar-link {{ request()->is('dbmanager/settings') ? 'active' : '' }}">
      <span class="s-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg></span>
      <span class="ltext">Settings</span>
    </a>
    <a href="/dbmanager/logout" class="sidebar-link" onclick="return confirm('Sign out of DB Manager?')">
      <span class="s-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
      <span class="ltext">Sign Out</span>
    </a>
  </div>

</nav>

<!-- ═══ Main ═══════════════════════════════════════════════════════════ -->
<div class="main">
  <div class="content">

    @if(session('success'))
    <div class="alert alert-success">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      {{ session('error') }}
    </div>
    @endif

    @yield('content')
  </div>
</div>

<script>
document.querySelectorAll('[data-confirm]').forEach(el=>{el.addEventListener('click',e=>{if(!confirm(el.dataset.confirm))e.preventDefault()})});
document.querySelectorAll('[data-autosubmit]').forEach(el=>{el.addEventListener('change',()=>el.closest('form').submit())});
</script>
@yield('scripts')
</body>
</html>
