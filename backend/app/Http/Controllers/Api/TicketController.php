<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class TicketController extends Controller
{
    /**
     * Get user's tickets
     */
    public function index(Request $request)
    {
        $tickets = Ticket::with(['event', 'order'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($tickets);
    }

    /**
     * Get single ticket details
     */
    public function show(Request $request, string $id)
    {
        $ticket = Ticket::with(['event', 'order', 'user'])
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json($ticket);
    }

    /**
     * Get ticket QR code (SVG format)
     */
    public function qrCode(Request $request, string $id)
    {
        $ticket = Ticket::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        // Generate QR code
        $qrCode = $ticket->generateQrCode();

        return response($qrCode, 200)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'public, max-age=31536000'); // Cache 1 year
    }

    /**
     * Get ticket QR data (for scanner validation)
     */
    public function qrData(Request $request, string $id)
    {
        // TAMBAHKAN with(['event', 'event.venue']) AGAR DATA EVENT TERBAWA KE FRONTEND
        $ticket = Ticket::with(['event', 'event.venue'])
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json([
            'ticket' => $ticket,
            'qr_data' => $ticket->getQrData(),
            'qr_code_svg' => $ticket->generateQrCode(),
        ]);
    }

    /**
     * =========================================================================
     * Download E-Ticket (Limit 10x per hari - Ringan untuk DB)
     * =========================================================================
     */
    public function download(Request $request, string $id)
    {
        $user = $request->user();
        
        $ticket = Ticket::with(['event', 'order'])
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Gunakan Cache (Rate Limiter) Laravel, bukan update Database setiap kali
        // Key unik berdasarkan ID User dan ID Tiket. Limit 10 kali. Decay time 1 Hari (86400 detik)
        $rateLimitKey = 'download_ticket_' . $user->id . '_' . $ticket->id;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $hours = ceil($seconds / 3600);
            
            return response()->json([
                'message' => "Batas unduh harian tercapai (Maks 10x/hari). Silakan coba lagi dalam {$hours} jam.",
            ], 429);
        }

        // Tambah hit hitungan (Jika belum 10, izinkan lewat)
        RateLimiter::hit($rateLimitKey, 86400); // 86400 detik = 24 jam

        // Opsional: Tetap catat last_downloaded_at untuk keperluan audit admin, tapi tanpa limit ketat
        $ticket->update(['last_downloaded_at' => now()]);

        return response()->json([
            'message' => 'Tiket berhasil diunduh.',
            'data' => [
                'ticket' => $ticket,
                'qr_code_svg' => $ticket->generateQrCode(),
            ]
        ]);
    }
}