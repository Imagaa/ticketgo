<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizerProfileCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->hasRole('organizer')) {
            $profile = $user->organizerProfile;

            if (!$profile) {
                return response()->json([
                    'error'   => 'PROFILE_INCOMPLETE',
                    'message' => 'Anda wajib melengkapi Profil Organizer sebelum dapat mengelola event.',
                ], 403);
            }

            // TAMBAHKAN INI: cek status verifikasi
            if ($profile->status !== 'verified') {
                return response()->json([
                    'error'   => 'PROFILE_NOT_VERIFIED',
                    'message' => 'Profil Organizer Anda sedang dalam proses verifikasi admin. Harap tunggu persetujuan.',
                ], 403);
            }
        }

        return $next($request);
    }
}