<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventController extends Controller
{
    /**
     * Get all published events (public)
     */
public function index(Request $request)
    {
        // KITA HAPUS ->upcoming() AGAR SEMUA EVENT PUBLISHED MUNCUL
        $query = Event::with(['category', 'venue', 'organizer'])
            ->published(); 

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by city
        if ($request->has('city')) {
            $query->whereHas('venue', function ($q) use ($request) {
                $q->where('city', 'like', '%' . $request->city . '%');
            });
        }

        // Search by title
        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        // Featured first
        $query->orderByDesc('is_featured')->orderBy('start_date');

        $events = $query->paginate(12);

        return response()->json($events);
    }

    /**
     * Get single event (public)
     */
    public function show($id)
    {
        $event = Event::with(['category', 'venue', 'organizer', 'ticketTiers'])
            ->where('status', 'published')
            ->where(function($q) use ($id) {
                $q->where('id', $id)->orWhere('slug', $id);
            })
            ->firstOrFail();

        return response()->json(['data' => $event]);
    }


    /**
     * Create new event (organizer/admin only)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'venue_id' => 'required|exists:venues,id',
            'start_date' => 'required|date|after:now',
            'end_date' => 'required|date|after:start_date',
            'total_seats' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'is_free' => 'boolean',
            'banner_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        // Generate slug from title
        $validated['slug'] = Str::slug($validated['title']) . '-' . Str::random(6);
        $validated['organizer_id'] = auth()->id();
        $validated['available_seats'] = $validated['total_seats'];
        $validated['status'] = 'draft';

        // Handle image upload
        if ($request->hasFile('banner_image')) {
            $path = $request->file('banner_image')->store('events', 'public');
            $validated['banner_image'] = $path;
        }

        $event = Event::create($validated);
        $event->load(['category', 'venue', 'organizer']);

        return response()->json([
            'message' => 'Event created successfully',
            'data' => $event,
        ], 201);
    }

    /**
     * Update event (organizer can update own, admin can update all)
     */
    public function update(Request $request, Event $event)
    {
        // Check authorization
        if (auth()->id() !== $event->organizer_id && !auth()->user()->hasRole('admin')) {
            return response()->json([
                'message' => 'Unauthorized to update this event.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'string|max:255',
            'description' => 'string',
            'category_id' => 'exists:categories,id',
            'venue_id' => 'exists:venues,id',
            'start_date' => 'date|after:now',
            'end_date' => 'date|after:start_date',
            'total_seats' => 'integer|min:1',
            'price' => 'numeric|min:0',
            'is_free' => 'boolean',
            'banner_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        // Handle image upload
        if ($request->hasFile('banner_image')) {
            // Delete old image if exists
            if ($event->banner_image) {
                Storage::disk('public')->delete($event->banner_image);
            }
            $path = $request->file('banner_image')->store('events', 'public');
            $validated['banner_image'] = $path;
        }

        $event->update($validated);
        $event->load(['category', 'venue', 'organizer']);

        return response()->json([
            'message' => 'Event updated successfully',
            'data' => $event,
        ]);
    }

    /**
     * Delete event (Berjenjang: Cancel -> Cooldown 7 Hari -> Soft Delete)
     */
    public function destroy(Event $event)
    {
        $user = auth()->user();
        $isAdmin = $user->hasRole('admin');

        // Pengecekan Hak Akses
        if (!$isAdmin && $user->id !== $event->organizer_id) {
            return response()->json([
                'message' => 'Unauthorized to delete this event.',
            ], 403);
        }

        // FASE 1: Jika event belum pernah minta dihapus
        if (!$event->delete_requested_at) {
            $event->update([
                'status' => 'cancelled', // Sembunyikan otomatis dari halaman Publik
                'delete_requested_at' => now(), // Mulai timer 7 hari
            ]);

            return response()->json([
                'message' => 'Event disembunyikan dari publik. Menunggu masa pending 7 hari sebelum bisa dihapus dari dashboard.',
                'status' => 'pending_delete'
            ]);
        }

        // FASE 2: Jika sudah minta dihapus, cek timer 7 hari
        $daysPassed = now()->diffInDays($event->delete_requested_at);
        
        // Organizer tertahan timer, tapi Admin bisa mem-bypass timer ini kapan saja
        if (!$isAdmin && $daysPassed < 7) {
            $daysLeft = 7 - $daysPassed;
            return response()->json([
                'message' => "Event masih dalam masa pending. Sisa waktu: {$daysLeft} hari sebelum dapat dihapus permanen.",
            ], 403);
        }

        // FASE 3: Lakukan Soft Delete 
        // (Data hilang dari dashboard EO, tapi seluruh data & transaksi tetap utuh di Database untuk Admin)
        $event->delete();

        return response()->json([
            'message' => 'Event berhasil dihapus dari dashboard Anda.',
            'status' => 'soft_deleted'
        ]);
    }

    /**
     * Get organizer's own events
     */
    public function myEvents()
    {
        $events = Event::with(['category', 'venue'])
            ->where('organizer_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate(12);

        return response()->json($events);
    }

    /**
     * Request publish event (organizer)
     */
    public function requestPublish(Event $event)
    {
        // Check authorization
        if (auth()->id() !== $event->organizer_id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        if ($event->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft events can request publishing.',
            ], 400);
        }

        $event->update(['status' => 'pending_approval']);

        return response()->json([
            'message' => 'Event submitted for approval',
            'data' => $event,
        ]);
    }

    /**
     * Approve event (admin only)
     */
    public function approve(Event $event)
    {
        if ($event->status !== 'pending_approval') {
            return response()->json([
                'message' => 'Only pending events can be approved.',
            ], 400);
        }

        $event->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        return response()->json([
            'message' => 'Event approved and published',
            'data' => $event,
        ]);
    }
    /**
     * Homepage: Event Unggulan (is_featured = true)
     */
    public function featured()
    {
        $events = Event::with(['category', 'venue', 'organizer', 'ticketTiers'])
            ->published()
            ->where('is_featured', true)
            ->orderBy('start_date')
            ->limit(8)
            ->get();

        return response()->json($events);
    }

    /**
     * Homepage: Menjelang Penutupan (sale_end dalam 8 hari ke depan)
     */
    public function closingSoon()
    {
        $events = Event::with(['category', 'venue', 'organizer', 'ticketTiers'])
            ->published()
            ->where('end_date', '>', now())
            ->where('end_date', '<=', now()->addDays(8))
            ->orderBy('end_date')
            ->limit(8)
            ->get();

        return response()->json($events);
    }

    /**
     * Homepage: Event Terbaru
     */
    public function latest()
    {
        $events = Event::with(['category', 'venue', 'organizer', 'ticketTiers'])
            ->published()
            ->orderByDesc('published_at')
            ->limit(8)
            ->get();

        return response()->json($events);
    }
}
