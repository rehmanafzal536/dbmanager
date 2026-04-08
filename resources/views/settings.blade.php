@extends('dbmanager::layout')
@section('content')

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px">
  <div>
    <h1 style="font-size:22px;font-weight:800;color:#111827">Settings</h1>
    <p style="font-size:13px;color:#6b7280;margin-top:3px">Manage credentials and connection info</p>
  </div>
  <a href="/dbmanager" class="btn btn-secondary btn-sm">&#8592; Back</a>
</div>

<div style="max-width:520px">

  <div class="card">
    <div class="card-header">
      <span style="display:flex;align-items:center;gap:8px">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        Change Login Credentials
      </span>
    </div>
    <div class="card-body">
      <div class="alert alert-info" style="margin-bottom:18px">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span>Saved to <code style="background:#dbeafe;padding:1px 6px;border-radius:3px">.env</code> as <code style="background:#dbeafe;padding:1px 6px;border-radius:3px">DBMANAGER_USERNAME</code> and <code style="background:#dbeafe;padding:1px 6px;border-radius:3px">DBMANAGER_PASSWORD</code></span>
      </div>

      <form method="POST" action="/dbmanager/settings/update">
        @csrf
        <div style="margin-bottom:16px">
          <label class="form-label">Current Username</label>
          <input type="text" class="form-control" value="{{ config('dbmanager.username') }}" disabled style="background:#f9fafb;color:#6b7280">
        </div>
        <div style="border-top:1px solid #e5e7eb;margin:18px 0"></div>
        <div style="margin-bottom:16px">
          <label class="form-label">New Username <span style="color:#dc2626">*</span></label>
          <input type="text" name="username" class="form-control" value="{{ old('username', config('dbmanager.username')) }}" required minlength="3">
          @error('username')<div style="color:#dc2626;font-size:12px;margin-top:4px">{{ $message }}</div>@enderror
        </div>
        <div style="margin-bottom:16px">
          <label class="form-label">New Password <span style="color:#dc2626">*</span></label>
          <input type="password" name="password" class="form-control" placeholder="Min. 4 characters" required minlength="4">
          @error('password')<div style="color:#dc2626;font-size:12px;margin-top:4px">{{ $message }}</div>@enderror
        </div>
        <div style="margin-bottom:22px">
          <label class="form-label">Confirm Password <span style="color:#dc2626">*</span></label>
          <input type="password" name="password_confirm" class="form-control" placeholder="Repeat password" required>
          @error('password_confirm')<div style="color:#dc2626;font-size:12px;margin-top:4px">{{ $message }}</div>@enderror
        </div>
        <div style="display:flex;gap:8px">
          <button type="submit" class="btn btn-primary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Save Credentials
          </button>
          <a href="/dbmanager" class="btn btn-secondary btn-sm">Cancel</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span style="display:flex;align-items:center;gap:8px">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4.03 3 9 3s9-1.34 9-3"/></svg>
        Connection Info
      </span>
    </div>
    <div class="card-body" style="padding:0">
      @php $conn = config('database.default'); @endphp
      <table class="dt">
        <tbody>
          <tr><td style="color:#6b7280;width:160px;font-weight:500">Driver</td><td><span class="badge badge-type">{{ strtoupper($conn) }}</span></td></tr>
          <tr><td style="color:#6b7280;font-weight:500">Database</td><td style="font-weight:600;color:#111827">{{ config("database.connections.{$conn}.database") }}</td></tr>
          @if($conn !== 'sqlite')
          <tr><td style="color:#6b7280;font-weight:500">Host</td><td style="font-weight:600;color:#111827">{{ config("database.connections.{$conn}.host") }}</td></tr>
          @endif
          <tr><td style="color:#6b7280;font-weight:500">Laravel</td><td style="font-weight:600;color:#111827">{{ app()->version() }}</td></tr>
          <tr><td style="color:#6b7280;font-weight:500">PHP</td><td style="font-weight:600;color:#111827">{{ PHP_VERSION }}</td></tr>
        </tbody>
      </table>
    </div>
  </div>

</div>
@endsection
