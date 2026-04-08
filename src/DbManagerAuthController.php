<?php

namespace Devtoolkit\DbManager;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DbManagerAuthController extends Controller
{
    public function showLogin()
    {
        if (session(config('dbmanager.session_key'))) {
            return redirect('/dbmanager');
        }
        return view('dbmanager::login');
    }

    public function login(Request $request)
    {
        if (
            $request->input('username') === config('dbmanager.username') &&
            $request->input('password') === config('dbmanager.password')
        ) {
            session([config('dbmanager.session_key') => true]);
            return redirect('/dbmanager');
        }
        return back()->withErrors(['credentials' => 'Invalid username or password.'])->withInput();
    }

    public function logout()
    {
        session()->forget(config('dbmanager.session_key'));
        return redirect('/dbmanager/login');
    }

    public function showSettings()
    {
        $tables = app(\Devtoolkit\DbManager\DbManagerController::class)->getTablesPublic();
        return view('dbmanager::settings', compact('tables'));
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'username'         => 'required|string|min:3',
            'password'         => 'required|string|min:4',
            'password_confirm' => 'required|same:password',
        ]);

        $envPath = base_path('.env');
        if (!file_exists($envPath)) {
            return back()->with('error', '.env file not found.');
        }

        $env = file_get_contents($envPath);
        $env = $this->setEnvValue($env, 'DBMANAGER_USERNAME', $request->input('username'));
        $env = $this->setEnvValue($env, 'DBMANAGER_PASSWORD', $request->input('password'));
        file_put_contents($envPath, $env);

        try { \Artisan::call('config:clear'); } catch (\Exception $e) {}

        return back()->with('success', 'Credentials updated. Please log in again.');
    }

    private function setEnvValue(string $env, string $key, string $value): string
    {
        $escaped = str_contains($value, ' ') ? "\"{$value}\"" : $value;
        if (preg_match("/^{$key}=.*/m", $env)) {
            return preg_replace("/^{$key}=.*/m", "{$key}={$escaped}", $env);
        }
        return $env . "\n{$key}={$escaped}";
    }
}
