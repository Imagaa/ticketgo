<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * Get user profile
     */
    public function show(Request $request)
    {
        return response()->json([
            'data' => $request->user()
        ]);
    }

    /**
     * Update user profile
     */
    public function update(Request $request)
    {
        $user = $request->user();

        // LOGIKA LOCK PROFILE: Cek apakah user punya tiket valid untuk event yang belum selesai
        $hasActiveTickets = $user->tickets()
            ->where('status', 'valid')
            ->whereHas('event', function ($query) {
                // Asumsi field end_date ada di tabel events
                $query->where('end_date', '>=', now());
            })->exists();

        if ($hasActiveTickets) {
            return response()->json([
                'message' => 'Profil terkunci. Anda memiliki tiket aktif untuk event yang belum selesai. Profil baru bisa diubah setelah acara selesai.',
                'code' => 'PROFILE_LOCKED'
            ], 403);
        }

        // Validasi input
        $validator = Validator::make($request->all(), [
            'nickname' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'avatar_url' => 'nullable|url|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Update data
        $user->update($validator->validated());

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'data' => $user
        ]);
    }
}