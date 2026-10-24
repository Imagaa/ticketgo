<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\OrganizerProfile;
use Illuminate\Http\Request;
use App\Models\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\OrganizerInvitation;
use Illuminate\Support\Facades\Cache;

class OrganizerController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('organizerProfile')
            ->whereHas('roles', fn($q) => $q->where('name', 'organizer'))
            ->withCount('events')
            ->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhereHas('organizerProfile', fn($q) =>
                    $q->where('organization_name', 'like', "%{$search}%")
                );
            });
        }

        $organizers = $query->paginate(15)->through(function($user) {
            $profile = $user->organizerProfile;
            
            // Cek apakah punya Magic Link yang masih aktif
            $isMagicLinkActive = false;
            if ($profile && $profile->magic_link_token) {
                if (!$profile->magic_link_expires_at || now()->isBefore($profile->magic_link_expires_at)) {
                    $isMagicLinkActive = true;
                }
            }

            return [
                'id'                => $user->id,
                'name'              => $user->name,
                'email'             => $user->email,
                'is_active'         => $user->is_active ?? true,
                'created_at'        => $user->created_at,
                'has_active_link'   => $isMagicLinkActive,
                'link_expires_at'   => $profile->magic_link_expires_at ?? null,
                'organizer_profile' => $profile ? [
                    'organization_name' => $profile->organization_name,
                    'phone'             => $profile->phone,
                    'description'       => $profile->description,
                    'logo_path'         => $profile->logo_path,
                    'total_events'      => $user->events_count,
                ] : null,
            ];
        });

        return response()->json($organizers);
    }

    public function toggleActive(User $user)
    {
        $user->update(['is_active' => !$user->is_active]);

        if (!$user->is_active) {
            $user->tokens()->delete(); 
        }

        return response()->json([
            'message' => $user->is_active ? 'Akses Organizer berhasil dipulihkan.' : 'Akses Organizer berhasil ditutup.',
            'is_active' => $user->is_active
        ]);
    }

    public function destroy(User $user)
    {
        $user->tokens()->delete(); 
        $user->update(['is_active' => false]);

        return response()->json([
            'message' => 'Akses Organizer berhasil ditutup. Data event dan transaksi mereka tetap aman.'
        ]);
    }

    public function generateMagicLink(Request $request, User $user)
    {
        $days = $request->input('days', 7);
        
        $token = Str::random(40);
        $pin = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = $days > 0 ? now()->addDays($days) : null;

        $user->organizerProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'magic_link_token' => $token,
                'magic_link_pin' => Hash::make($pin),
                'magic_link_expires_at' => $expiresAt,
                'magic_link_failed_attempts' => 0,
                'magic_link_locked_until' => null,
            ]
        );

        $frontendBaseUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');
        $frontendUrl = $frontendBaseUrl . '/shared/organizer/' . $token;

        return response()->json([
            'message'    => 'Link Rahasia berhasil dibuat.',
            'magic_link' => $frontendUrl,
            'pin'        => $pin,
            'expires_at' => $expiresAt ? $expiresAt->format('d M Y, H:i') : 'Aktif Selamanya',
        ]);
    }

    /**
 * Generate invite link untuk calon organizer baru (admin only)
 */
public function generateInvite(Request $request)
{
    $request->validate([
        'days' => 'nullable|integer|min:1|max:30',
    ]);

    $days    = $request->input('days', 7);
    $token   = Str::random(48);
    $expires = now()->addDays($days);

    OrganizerInvitation::create([
        'token'      => $token,
        'created_by' => $request->user()->id,
        'expires_at' => $expires,
    ]);

    $frontendBase = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
    $inviteLink   = $frontendBase . '/register-organizer/' . $token;

    return response()->json([
        'invite_link' => $inviteLink,
        'expires_at'  => $expires->format('d M Y, H:i') . ' WIB',
        'message'     => 'Link undangan berhasil dibuat.',
    ]);
}

    /**
     * Validasi token invite (dipanggil frontend saat halaman dibuka)
     */
    public function checkInvite(string $token)
    {
    $invitation = OrganizerInvitation::where('token', $token)->first();

    if (!$invitation) {
        return response()->json(['message' => 'Link undangan tidak valid.'], 404);
    }

    if (!$invitation->isValid()) {
        $reason = $invitation->used_at
            ? 'Link undangan ini sudah pernah digunakan.'
            : 'Link undangan ini sudah kedaluwarsa.';

        return response()->json(['message' => $reason], 410);
    }

    return response()->json([
        'valid'      => true,
        'expires_at' => $invitation->expires_at->format('d M Y, H:i'),
    ]);
    }

    public function sharedData(Request $request, $id)
    {
        $token = $id;

        $profile = OrganizerProfile::where('magic_link_token', $token)->with('user')->first();

        if (!$profile) {
            return response()->json(['message' => 'Link tidak valid atau tidak ditemukan.'], 404);
        }

        if ($profile->magic_link_expires_at && now()->isAfter($profile->magic_link_expires_at)) {
            return response()->json(['message' => 'Mohon maaf, masa berlaku tautan ini sudah kedaluwarsa.'], 410);
        }

        if ($profile->magic_link_locked_until && now()->isBefore($profile->magic_link_locked_until)) {
            $minutesLeft = now()->diffInMinutes($profile->magic_link_locked_until) + 1;
            return response()->json([
                'message' => "Terlalu banyak percobaan salah. Akses ditangguhkan demi keamanan. Silakan coba lagi dalam {$minutesLeft} menit."
            ], 429);
        }

        if (!$request->has('pin')) {
            return response()->json([
                'requires_pin'   => true,
                'organizer_name' => $profile->organization_name ?? $profile->user->name
            ]);
        }

        if (!Hash::check($request->pin, $profile->magic_link_pin)) {
            $attempts = $profile->magic_link_failed_attempts + 1;
            
            if ($attempts >= 5) {
                $profile->update([
                    'magic_link_failed_attempts' => $attempts,
                    'magic_link_locked_until'    => now()->addHour()
                ]);
                return response()->json(['message' => 'Terlalu banyak percobaan salah. Akses Anda telah dikunci selama 1 Jam.'], 429);
            }

            $profile->update(['magic_link_failed_attempts' => $attempts]);
            $sisa = 5 - $attempts;
            
            return response()->json(['message' => "PIN tidak sesuai. Sisa percobaan Anda: {$sisa} kali lagi."], 401);
        }

        if ($profile->magic_link_failed_attempts > 0) {
            $profile->update([
                'magic_link_failed_attempts' => 0,
                'magic_link_locked_until'    => null
            ]);
        }

        $user = $profile->user;
        
        $events = Event::where('organizer_id', $user->id)
            ->with(['orders' => function($q) {
                // HAPUS RELASI ticketTier DARI SINI (karena tidak exist di model Order)
                $q->whereIn('status', ['paid', 'confirmed', 'success', 'settlement'])
                  ->with(['user', 'tickets.ticketTier']); 
            }])
            ->get()
            ->map(function ($event) {
                $ticketsSold = 0;
                $totalRevenue = 0;
                $buyers = [];

                foreach ($event->orders as $order) {
                    $ticketsCount = $order->quantity ?? $order->tickets->count() ?? 0;
                    $ticketsSold += $ticketsCount;
                    $totalRevenue += (float) ($order->total ?? 0);
                    
                    $ticketTypeString = [];
                    
                    // --- PERBAIKAN LOGIKA PEMBACA NAMA TIKET ---
                    
                    // Prioritas 1: Baca langsung dari JSON ticket_details (Format Baru Checkout)
                    if (!empty($order->ticket_details) && is_array($order->ticket_details) && isset($order->ticket_details['tier_name'])) {
                        $ticketTypeString[] = $order->ticket_details['tier_name'] . ' (x' . $ticketsCount . ')';
                    } 
                    // Prioritas 2: Dari array tabel tickets (Sistem Paling Lama)
                    elseif ($order->tickets && $order->tickets->count() > 0) {
                        $ticketTypes = [];
                        foreach ($order->tickets as $ticket) {
                            $tierName = $ticket->ticketTier->name ?? 'Reguler';
                            if (!isset($ticketTypes[$tierName])) $ticketTypes[$tierName] = 0;
                            $ticketTypes[$tierName]++;
                        }
                        foreach ($ticketTypes as $name => $qty) {
                            $ticketTypeString[] = $name . ' (x' . $qty . ')';
                        }
                    } 
                    // Prioritas 3: Fallback membaca JSON format lama (Array of Arrays)
                    elseif (!empty($order->ticket_details) && is_array($order->ticket_details)) {
                        foreach ($order->ticket_details as $detail) {
                            if (is_array($detail)) {
                                $tierName = $detail['name'] ?? $detail['tier_name'] ?? $detail['ticket_name'] ?? 'Kategori Tiket';
                                $qty = $detail['quantity'] ?? $detail['qty'] ?? 1;
                                $ticketTypeString[] = $tierName . ' (x' . $qty . ')';
                            }
                        }
                    } 
                    // Prioritas 4: Gagal menemukan nama
                    else {
                        $ticketTypeString[] = 'Tiket (x' . $ticketsCount . ')';
                    }

                    $buyers[] = [
                        'order_code'    => $order->order_code ?? ('ORD-' . str_pad($order->id, 4, '0', STR_PAD_LEFT)),
                        'name'          => $order->user->name ?? 'Guest',
                        'ticket_types'  => implode(', ', $ticketTypeString),
                        'ticket_amount' => $ticketsCount,
                        'purchase_date' => $order->created_at->format('Y-m-d H:i')
                    ];
                }

                return [
                    'id'            => $event->id,
                    'title'         => $event->title,
                    'status'        => $event->status,
                    'start_date'    => $event->start_date,
                    'total_revenue' => $totalRevenue,
                    'tickets_sold'  => $ticketsSold,
                    'buyers'        => $buyers
                ];
            });

        return response()->json([
            'organizer_name' => $profile->organization_name ?? $user->name,
            'events'         => $events
        ]);
    }
}