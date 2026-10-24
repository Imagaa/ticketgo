<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Password;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;
use App\Models\OrganizerInvitation;

class AuthController extends Controller
{
    /**
     * Register new user
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Kunci akun secara default (menunggu OTP)
        // Kita set manual agar menghindari error $fillable di model
        $user->is_active = false;
        $user->save();

        // Assign default role 'user'
        $user->assignRole('user');

        // Jangan berikan token dulu karena belum OTP
        return response()->json([
            'message' => 'Registration successful. Please verify OTP.',
            'user' => $user,
        ], 201);
    }

    /**
     * Register akun organizer via invite link dari admin
     */
public function registerOrganizer(Request $request, string $token)
{
    // 1. Validasi token undangan
    $invitation = OrganizerInvitation::where('token', $token)->first();

    if (!$invitation || !$invitation->isValid()) {
        return response()->json([
            'message' => 'Link undangan tidak valid atau sudah kedaluwarsa.',
        ], 410);
    }

    // 2. Validasi input
    $validated = $request->validate([
        'name'                  => 'required|string|max:255',
        'email'                 => 'required|string|email|max:255|unique:users',
        'password'              => 'required|string|min:8|confirmed',
    ]);

    // 3. Buat akun (non-aktif, menunggu OTP)
    $user = User::create([
        'name'     => $validated['name'],
        'email'    => $validated['email'],
        'password' => Hash::make($validated['password']),
    ]);

    $user->is_active = false;
    $user->save();

    // 4. Assign role organizer (bukan 'user')
    $user->assignRole('organizer');

    // 5. Tandai undangan sudah dipakai
    //    Belum di-used_at dulu — baru ditandai setelah OTP berhasil
    //    Simpan relasi invite ke cache sementara (15 menit, cukup untuk OTP)
    \Illuminate\Support\Facades\Cache::put(
        'organizer_invite_for_' . $user->id,
        $invitation->id,
        now()->addMinutes(15)
    );

    return response()->json([
        'message' => 'Akun berhasil dibuat. Silakan cek email Anda untuk kode OTP.',
        'email'   => $user->email,
    ], 201);
}

    /**
     * Login user
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'source' => 'nullable|string|in:web,organizer,admin' // Portal asal login
        ]);

        $user = User::where('email', $request->email)->first();

        // 1. Jika Email Belum Terdaftar (Untuk trigger auto-redirect di Frontend)
        if (!$user) {
            return response()->json([
                'message' => 'Akun belum terdaftar',
                'error_code' => 'USER_NOT_FOUND'
            ], 404);
        }

        // 2. Jika Password Salah
        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password yang Anda masukkan salah.'],
            ]);
        }

        // 3. Jika belum OTP (Akun belum aktif)
        if (!$user->is_active) {
            return response()->json([
                'message' => 'Akun belum diverifikasi.',
                'error_code' => 'NOT_VERIFIED'
            ], 403);
        }

        $source = $request->input('source', 'web');

        // Separasi Gatekeeper
        if ($source === 'web' && $user->hasAnyRole(['admin', 'organizer'])) {
            throw ValidationException::withMessages([
                'email' => ['Akses ditolak. Silakan login melalui dashboard khusus Organizer/Admin.'],
            ]);
        }

        if ($source === 'organizer' && !$user->hasRole('organizer')) {
            throw ValidationException::withMessages([
                'email' => ['Akses ditolak. Akun ini tidak terdaftar sebagai Organizer.'],
            ]);
        }

        if ($source === 'admin' && !$user->hasRole('admin')) {
            throw ValidationException::withMessages([
                'email' => ['Akses ditolak. Anda bukan Admin.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user->load('roles', 'organizerProfile'),
            'token' => $token,
        ]);
    }

    /**
     * Verifikasi OTP (Placeholder)
     */
    public function verifyOtp(Request $request)
    {
    $request->validate([
        'email' => 'required|email',
        'otp'   => 'required|string',
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user) {
        return response()->json(['message' => 'User not found'], 404);
    }

    if ($user->is_active) {
        return response()->json(['message' => 'Akun sudah diverifikasi sebelumnya.'], 400);
    }

    // PLACEHOLDER OTP — ganti dengan mailer nanti
    if ($request->otp !== '123456') {
        throw ValidationException::withMessages([
            'otp' => ['Kode OTP salah atau sudah kedaluwarsa.'],
        ]);
    }

    // Aktifkan akun
    $user->is_active          = true;
    $user->email_verified_at  = now();
    $user->save();

    // Tandai undangan sebagai 'used' jika user ini dari invite organizer
    $invitationId = \Illuminate\Support\Facades\Cache::pull('organizer_invite_for_' . $user->id);
    if ($invitationId) {
        OrganizerInvitation::where('id', $invitationId)->update([
            'used_at' => now(),
            'used_by' => $user->id,
        ]);
    }

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message' => 'Verifikasi sukses',
        'user'    => $user->load('roles', 'organizerProfile'),
        'token'   => $token,
    ]);
    }


    /**
     * Send password reset link (forgot password)
     */
    public function forgotPassword(Request $request)
    {
        // 1. Validasi email
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // 2. Coba kirim reset link melalui broker "users"
        //    Kita sengaja IGNORE status untuk tidak bocorkan apakah email ada atau tidak
        $status = Password::broker('users')->sendResetLink(
            $request->only('email')
        );

        // 3. Selalu balas pesan generic (anti user enumeration)
        return response()->json([
            'message' => 'If this email exists, a password reset link has been sent.',
        ]);
    }

        /**
     * Reset password using token (from email link)
     */
    public function resetPassword(Request $request)
    {
        // 1. Validasi input
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // 2. Gunakan password broker "users" untuk verifikasi token & reset password
        $status = Password::broker('users')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(str()->random(60));

                $user->save();
            }
        );

        // 3. Tangani hasil
        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Password has been reset successfully.',
            ]);
        }

        // Jika gagal (token invalid / email tidak cocok / token expired)
        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }


    /**
     * Get authenticated user
     */
    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load('roles', 'permissions'),
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Redirect to Google OAuth
     */
    public function redirectToGoogle(Request $request)
    {
        // 1. Tangkap parameter intended_url dari frontend (default ke '/')
        $intended = $request->query('intended_url', '/');
        
        // 2. Encode state agar aman saat dikirim melewati server Google
        $state = base64_encode($intended);

        return Socialite::driver('google')
            ->stateless()
            ->with(['state' => $state]) // Titipkan state ke Google
            ->redirect();
    }

    /**
     * Handle Google Callback
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            // 1. Ambil kembali state yang dititipkan dari Google
            $state = $request->input('state');
            $intendedUrl = $state ? base64_decode($state) : '/';

            // 2. Proses User
            $googleUser = Socialite::driver('google')->stateless()->user();
            $user = User::where('email', $googleUser->getEmail())->first();

            $superAdminEmail = env('SUPER_ADMIN_EMAIL');

            if (!$user) {
                $user = User::create([
                    'name'             => $googleUser->getName(),
                    'email'            => $googleUser->getEmail(),
                    'password'         => Hash::make(\Illuminate\Support\Str::random(24)),
                    'is_active'        => true,
                    'email_verified_at' => now(),
                ]);

                // Jika email cocok dengan SUPER_ADMIN_EMAIL, assign admin
                if ($superAdminEmail && $user->email === $superAdminEmail) {
                    $user->assignRole('admin');
                } else {
                    $user->assignRole('user');
                }
            } else {
                // User sudah ada — pastikan kalau emailnya super admin, role-nya benar
                if ($superAdminEmail && $user->email === $superAdminEmail && !$user->hasRole('admin')) {
                    $user->assignRole('admin');
                }

                if (!$user->is_active) {
                    $user->is_active = true;
                    $user->email_verified_at = now();
                    $user->save();
                }
            }
            $token = $user->createToken('auth_token')->plainTextToken;

            // 3. Pastikan URL terbaca dengan benar dari Railway Variables
            $frontendBase = env('FRONTEND_URL', 'http://localhost:3000');
            $frontendUrl = rtrim($frontendBase, '/') . '/auth/google/callback';
            
            $userData = rawurlencode(json_encode($user->load('roles', 'organizerProfile')));
            $intendedEncoded = rawurlencode($intendedUrl);
            
            // 4. Lempar kembali ke frontend dengan tambahan parameter intended
            return redirect()->away($frontendUrl . "?token={$token}&user={$userData}&intended={$intendedEncoded}");

        } catch (\Exception $e) {
            // Tambahkan baris log ini
            \Illuminate\Support\Facades\Log::error('Google OAuth Failed: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
            
            $frontendBase = env('FRONTEND_URL', 'http://localhost:3000');
            $frontendLoginUrl = rtrim($frontendBase, '/') . '/login';
            return redirect()->away($frontendLoginUrl . '?error=GoogleAuthFailed');
        }
    }
}