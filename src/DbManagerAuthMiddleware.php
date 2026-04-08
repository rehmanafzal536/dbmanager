<?php

namespace Devtoolkit\DbManager;

use Closure;
use Illuminate\Http\Request;

class DbManagerAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('dbmanager/login') || $request->is('dbmanager/login/*')) {
            return $next($request);
        }
        if (!session(config('dbmanager.session_key'))) {
            return redirect('/dbmanager/login');
        }
        return $next($request);
    }
}
