<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GeographicScopeMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->hasRole('Business Owner')) {
            // Apply global scope to restrict queries to user's assigned regions
            $regionIds = $user->staffProfile?->regions()->pluck('regions.id')->toArray() ?? [];
            
            // In a real application, you would apply this to a global context or scope.
            // For example, setting a container binding or tenant context:
            app()->instance('current_user_regions', $regionIds);
        }

        return $next($request);
    }
}
