<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /**
     * Get all events (with filters)
     */
    public function index(Request $request)
    {
        $query = Event::with(['category', 'venue', 'organizer']);
        $query = Event::withTrashed()->with(['category', 'venue', 'organizer']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by organizer
        if ($request->has('organizer_id')) {
            $query->where('organizer_id', $request->organizer_id);
        }

        // Search by title
        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $events = $query->paginate($request->get('per_page', 15));

        return response()->json($events);
    }

    /**
     * Approve event
     */
    public function approve(Request $request, Event $event)
    {
        if ($event->status !== 'pending_approval') {
            return response()->json([
                'message' => 'Event is not pending approval',
            ], 400);
        }

        $event->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        return response()->json([
            'message' => 'Event approved successfully',
            'event' => $event,
        ]);
    }

    /**
     * Reject event
     */
    public function reject(Request $request, Event $event)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if ($event->status !== 'pending_approval') {
            return response()->json([
                'message' => 'Event is not pending approval',
            ], 400);
        }

        $event->update([
            'status' => 'draft',
            // Store rejection reason (you may need to add this column)
        ]);

        // TODO: Send notification to organizer

        return response()->json([
            'message' => 'Event rejected',
            'event' => $event,
        ]);
    }

    /**
     * Feature/unfeature event
     */
    public function toggleFeature(Event $event)
    {
        $event->update([
            'is_featured' => !$event->is_featured,
        ]);

        return response()->json([
            'message' => $event->is_featured ? 'Event featured' : 'Event unfeatured',
            'event' => $event,
        ]);
    }

    /**
     * Dewa Mode: 4-Stage Delete Flow
     */
    public function destroy(Request $request, $id)
    {
        // Panggil withTrashed agar Event yang sudah di-soft-delete tetap terbaca
        $event = Event::withTrashed()->findOrFail($id);
        $action = $request->query('action', 'hide');

        if ($action === 'hide') {
            $event->update([
                'status' => 'draft', 
                'delete_requested_at' => now()
            ]);
            return response()->json([
                'message' => 'Event disembunyikan. Menunggu pending 7 hari.',
                'event' => $event // Kirim data terbaru ke frontend
            ]);
        }

        if ($action === 'force_delete') {
            if (!$event->trashed()) {
                $event->delete(); // Eksekusi Soft Delete
            }
            return response()->json([
                'message' => 'Override berhasil. Event di-Force Delete secara Soft.',
                'event' => $event->refresh() // Ambil data yang sudah ada deleted_at nya
            ]);
        }

        if ($action === 'clear_delete') {
            // PERBAIKAN: Gunakan 'cancelled' sebagai flag untuk menghindari Error Constraint SQL
            $event->update(['status' => 'cancelled']); 
            return response()->json([
                'message' => 'Event diberi flag Clear Delete. Menunggu pending 2 minggu.',
                'event' => $event
            ]);
        }

        if ($action === 'force_clear') {
            // PERBAIKAN: Hapus secara berantai dari anak terkecil (Bottom-Up)
            // 1. Hapus tiket yang sudah diterbitkan (karena ini berelasi ke Order & Event)
            $event->tickets()->forceDelete();
            
            // 2. Hapus tiket tier (kategori tiket)
            if (method_exists($event, 'ticketTiers')) {
                $event->ticketTiers()->forceDelete();
            }

            // 3. Hapus pesanan (karena berelasi ke Event)
            $event->orders()->forceDelete(); 
            
            // 4. Terakhir, Hapus Event-nya
            $event->forceDelete();
            
            return response()->json(['message' => 'WIPE OUT! Event dihapus permanen dari Database beserta isinya.']);
        }

        return response()->json(['message' => 'Invalid delete action'], 400);
    }
}