<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOnly
{
    /**
     * Handle an incoming request.
     * Redirect non-admins to their user dashboard.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return $next($request);
        }
        // If not authenticated, let the auth middleware deal with it upstream
        if (!$user) {
            return redirect()->route('login');
        }
        return redirect()->route('dashboard.user');
    }
}
