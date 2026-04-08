<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>DB Manager — Sign In</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;font-size:14px;background:#f0f2f5;color:#1a1d23;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}

.login-wrap{width:100%;max-width:420px}

.login-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:40px;box-shadow:0 4px 24px rgba(0,0,0,.08)}

.brand{display:flex;align-items:center;gap:14px;margin-bottom:32px}
.brand-icon{width:48px;height:48px;background:linear-gradient(135deg,#1d4ed8,#3b82f6);border-radius:12px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(29,78,216,.3);flex-shrink:0}
.brand-icon svg{color:#fff}
.brand-name{font-size:20px;font-weight:800;color:#111827;letter-spacing:-.3px}
.brand-sub{font-size:12.5px;color:#6b7280;margin-top:2px}

.form-group{margin-bottom:18px}
.form-label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px}
.input-wrap{position:relative}
.input-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9ca3af;pointer-events:none}
.form-control{width:100%;padding:11px 12px 11px 40px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:14px;color:#111827;background:#fff;transition:border-color .15s,box-shadow .15s}
.form-control:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12)}
.form-control::placeholder{color:#9ca3af}

.btn-login{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:12px 20px;border-radius:8px;border:none;cursor:pointer;font-size:14.5px;font-weight:700;background:linear-gradient(135deg,#1d4ed8,#3b82f6);color:#fff;transition:all .15s;box-shadow:0 4px 12px rgba(29,78,216,.25);margin-top:8px}
.btn-login:hover{background:linear-gradient(135deg,#1e40af,#2563eb);box-shadow:0 6px 16px rgba(29,78,216,.35);transform:translateY(-1px)}
.btn-login:active{transform:translateY(0)}

.alert-error{padding:12px 14px;border-radius:8px;margin-bottom:20px;font-size:13px;background:#fef2f2;border:1.5px solid #fecaca;color:#991b1b;display:flex;align-items:center;gap:8px}

.hint{margin-top:20px;text-align:center;font-size:12.5px;color:#9ca3af;padding-top:20px;border-top:1px solid #f3f4f6}
.hint code{background:#f3f4f6;padding:2px 7px;border-radius:4px;font-size:12px;color:#374151}
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-card">

    <div class="brand">
      <div class="brand-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4.03 3 9 3s9-1.34 9-3"/></svg>
      </div>
      <div>
        <div class="brand-name">DB Manager</div>
        <div class="brand-sub">Sign in to manage your database</div>
      </div>
    </div>

    @if(isset($errors) && $errors->has('credentials'))
    <div class="alert-error">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      {{ $errors->first('credentials') }}
    </div>
    @endif

    <form method="POST" action="/dbmanager/login">
      @csrf
      <div class="form-group">
        <label class="form-label">Username</label>
        <div class="input-wrap">
          <span class="input-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </span>
          <input type="text" name="username" class="form-control" value="{{ old('username') }}" placeholder="Enter username" autofocus required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <div class="input-wrap">
          <span class="input-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </span>
          <input type="password" name="password" class="form-control" placeholder="Enter password" required>
        </div>
      </div>
      <button type="submit" class="btn-login">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
        Sign In
      </button>
    </form>

    <div class="hint">
      Default credentials: <code>admin</code> / <code>secret</code>
    </div>
  </div>
</div>
</body>
</html>
