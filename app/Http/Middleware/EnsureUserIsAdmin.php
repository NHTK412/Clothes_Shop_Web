<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'ROLE_ADMIN') {
            abort(403, 'Chỉ quản trị viên được phép');
        }

        return $next($request);
    }
}
