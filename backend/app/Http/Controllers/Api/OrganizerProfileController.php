<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrganizerProfile;

class OrganizerProfileController extends Controller
{
    // Mengambil data profil EO saat ini
    public function show(Request $request)
    {
        $profile = $request->user()->organizerProfile;
        
        if (!$profile) {
            return response()->json([
                'is_completed' => false,
                'message' => 'Profil belum dilengkapi.'
            ], 404);
        }

        return response()->json([
            'is_completed' => true,
            'profile' => $profile
        ]);
    }

    // Menyimpan atau mengupdate profil EO
    public function store(Request $request)
    {
        $validated = $request->validate([
            'pic_name' => 'required|string|max:255',
            'ktp_number' => 'required|string|max:50',
            'address' => 'required|string',
            'city' => 'required|string|max:100',
            'display_name' => 'required|string|max:255',
            'logo_url' => 'nullable|url',
            'bank_name' => 'required|string|max:100',
            'bank_account_number' => 'required|string|max:100',
            'bank_account_name' => 'required|string|max:255',
        ]);

        $profile = OrganizerProfile::updateOrCreate(
            ['user_id' => $request->user()->id],
            $validated
        );

        return response()->json([
            'message' => 'Profil Organizer berhasil disimpan.',
            'profile' => $profile
        ]);
    }
}