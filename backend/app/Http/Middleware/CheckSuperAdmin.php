<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $superAdminEmail = env('SUPER_ADMIN_EMAIL');

        // Jika user belum login, atau emailnya BUKAN email super admin, tolak!
        if (!$user || $user->email !== $superAdminEmail) {
            return response()->json([
                'success' => false,
                'message' => 'Akses Terlarang: Anda bukan Super Admin yang sah.'
            ], 403);
        }

        return $next($request);
    }
}