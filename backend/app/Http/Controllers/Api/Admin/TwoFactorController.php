<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PragmaRX\Google2FALaravel\Support\Authenticator;

class TwoFactorController extends Controller
{
    /**
     * Meminta Kunci Rahasia & URL untuk QR Code
     */
    public function generate(Request $request)
    {
        $user = $request->user();
        $google2fa = app('pragmarx.google2fa');

        // Jika super admin belum punya secret key, kita buatkan yang baru
        if (!$user->google2fa_secret) {
            $user->google2fa_secret = $google2fa->generateSecretKey();
            $user->save();
        }

        // Buat URL khusus yang bisa dibaca oleh aplikasi Google Authenticator
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            'TicketGo Admin',
            $user->email,
            $user->google2fa_secret
        );

        return response()->json([
            'success' => true,
            'secret' => $user->google2fa_secret,
            'qr_code_url' => $qrCodeUrl, 
            'message' => 'Gunakan qr_code_url ini untuk di-render menjadi gambar QR di Frontend.'
        ]);
    }

    /**
     * Memverifikasi 6 Digit Angka dari HP
     */
    public function verify(Request $request)
    {
        $request->validate([
            'otp' => 'required|string|size:6',
        ]);

        $user = $request->user();
        $google2fa = app('pragmarx.google2fa');

        // Cek apakah 6 digit angka dari HP cocok dengan rahasia di database
        $isValid = $google2fa->verifyKey($user->google2fa_secret, $request->otp);

        if ($isValid) {
            // Tandai bahwa 2FA sudah aktif
            $user->is_2fa_enabled = true;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Autentikasi Dua Langkah berhasil diaktifkan!'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Kode OTP tidak valid atau sudah kedaluwarsa.'
        ], 400);
    }
}