<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Send signed-in users who land on another role's page to their own
     * dashboard instead of showing a 403.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user && ! in_array($user->role, $roles, true)) {
            return new RedirectResponse(route($user->homeRouteName()));
        }

        return $next($request);
    }
}
