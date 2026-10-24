<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        foreach ($roles as $role) {
            $roleList = explode('|', $role);
            foreach ($roleList as $r) {
                if ($user->hasRole(trim($r))) {
                    return $next($request);
                }
            }
        }

        return response()->json([
            'message' => 'Unauthorized. Insufficient role.',
        ], 403);
    }
}
