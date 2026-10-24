<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\Category;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\TicketTier;

class EventWizardController extends Controller
{
    public function saveStepOne(Request $request)
    {
        $isManual = $request->input('is_manual_save', false);

        $validator = Validator::make($request->all(), [
            'event_id'         => 'nullable|exists:events,id',
            'is_manual_save'   => 'boolean',
            'title'            => $isManual ? 'nullable|string|max:255' : 'required|string|max:255',
            'category_id'      => $isManual ? 'nullable|exists:categories,id' : 'required|exists:categories,id',
            'sales_start_date' => $isManual ? 'nullable|date' : 'required|date',
            'sales_end_date'   => $isManual ? 'nullable|date|after_or_equal:sales_start_date' : 'required|date|after:sales_start_date',
            'venue_id'         => 'nullable|exists:venues,id',
            'venue_name'       => $isManual ? 'nullable|string|max:255' : 'nullable|required_without:venue_id|string|max:255',
            'venue_address'    => 'nullable|string',
            'gmaps_url'        => 'nullable|url|max:255',
            'social_links'     => 'nullable|array',
            'local_tax_percentage'  => 'nullable|numeric|min:0|max:100',
            'max_tickets_per_order' => 'nullable|integer|min:1|max:10',
            // TAMBAHAN MULTI-TENANT (ARTIST & AFFILIATE)
            'artist_id'            => 'nullable|exists:users,id',
            'is_affiliate_enabled' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Terdapat isian yang belum lengkap atau tidak valid.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $userId = $request->user()->id;
        
        // TAMBAHKAN field artist_id dan is_affiliate_enabled
        $data = $request->only([
            'title', 'category_id', 'sales_start_date', 'sales_end_date',
            'venue_id', 'venue_name', 'venue_address', 'gmaps_url', 'social_links',
            'artist_id' 
        ]);
        
        // Pastikan format boolean aman
        $data['is_affiliate_enabled'] = $request->input('is_affiliate_enabled', false);

        try {
            if ($isManual) {
                if (empty($data['title'])) {
                    $data['title'] = 'Draft Event - ' . now()->format('d M Y, H:i');
                }
                if (empty($data['category_id'])) {
                    $firstCategory = Category::first();
                    $data['category_id'] = $firstCategory ? $firstCategory->id : null;
                }
            }

            $data['slug'] = Str::slug($data['title'] . '-' . Str::random(6));

            if ($request->has('event_id') && $request->event_id != null) {
                $event = Event::where('id', $request->event_id)
                              ->where('organizer_id', $userId)
                              ->firstOrFail();
                $event->update($data);
                $message = 'Draft event berhasil diperbarui.';
            } else {
                $data['organizer_id'] = $userId;
                $data['status']       = 'draft';
                $data['start_date']   = $data['sales_start_date'] ?? now();
                $data['end_date']     = $data['sales_end_date'] ?? now()->addDays(1);
                $data['is_free_event'] = false;
                $data['is_ppn_included'] = false;
                $data['total_seats']     = 0;
                $data['available_seats'] = 0;
                $data['price']           = 0;

                $event = Event::create($data);
                $event->refresh(); 
                
                $message = 'Event berhasil disimpan sebagai draft.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'event_id' => $event->id,
                    'status'   => $event->status,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan internal saat menyimpan draft.',
                'error_detail' => $e->getMessage() 
            ], 500);
        }
    }

    public function getWizardEvent(Request $request, $id)
    {
        $event = Event::with('ticketTiers')
                      ->where('id', $id)
                      ->where('organizer_id', $request->user()->id)
                      ->firstOrFail();

        return response()->json(['success' => true, 'data' => $event]);
    }

    public function saveStepTwo(Request $request)
    {
        $isManual = $request->input('is_manual_save', '0') == '1';

        $validator = Validator::make($request->all(), [
            'event_id'    => 'required|exists:events,id',
            'description' => $isManual ? 'nullable|string' : 'required|string',
            'start_date'  => $isManual ? 'nullable|date' : 'required|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'poster'      => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'seatmap'     => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Terdapat isian yang belum lengkap atau file terlalu besar.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $userId = $request->user()->id;

        try {
            $event = Event::where('id', $request->event_id)
                          ->where('organizer_id', $userId)
                          ->firstOrFail();

            $dataToUpdate = [
                'description' => $request->description,
                'start_date'  => $request->start_date,
                'end_date'    => $request->end_date ?? $request->start_date,
            ];

            if ($request->hasFile('poster')) {
                if ($event->poster_path) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($event->poster_path);
                }
                $dataToUpdate['poster_path'] = $request->file('poster')->store('events/posters', 'public');
            }

            if ($request->hasFile('seatmap')) {
                if ($event->seatmap_path) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($event->seatmap_path);
                }
                $dataToUpdate['seatmap_path'] = $request->file('seatmap')->store('events/seatmaps', 'public');
            }

            $event->update($dataToUpdate);

            return response()->json([
                'success' => true,
                'message' => 'Detail event berhasil disimpan.',
                'data' => ['event_id' => $event->id]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan internal saat menyimpan detail event.',
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }

    // ================= FUNGSI BARU UNTUK STEP 3 (ADVANCED TICKETING) =================
    public function saveStepThree(Request $request)
    {
        $isManual = $request->input('is_manual_save', false);

        // Tambahkan validasi untuk Advanced Rules
        $validator = Validator::make($request->all(), [
            'event_id'           => 'required|exists:events,id',
            'is_free_event'      => 'boolean',
            'is_ppn_included'    => 'boolean',
            'tickets'            => 'required|array|min:1',
            'tickets.*.name'     => $isManual ? 'nullable|string' : 'required|string',
            'tickets.*.price'    => 'required|numeric|min:0',
            'tickets.*.capacity' => 'required|numeric|min:1',
            
            // Aturan Lanjutan (Advanced Rules)
            'tickets.*.close_type'              => 'nullable|string|in:quota,date,both',
            'tickets.*.start_sales_at'          => 'nullable|date',
            'tickets.*.end_sales_at'            => 'nullable|date',
            'tickets.*.auto_open_after_tier_id' => 'nullable|string', // Bisa ID Sementara dari Frontend
            'tickets.*.is_visible_before_sales' => 'nullable|boolean',
            'tickets.*.is_active'               => 'nullable|boolean',

            'custom_fields'      => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Data tiket tidak valid.', 'errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $event = Event::where('id', $request->event_id)
                          ->where('organizer_id', $request->user()->id)
                          ->firstOrFail();

            // Hitung total kapasitas dari semua varian tiket
            $totalCapacity = 0;
            foreach ($request->tickets as $t) {
                $totalCapacity += (int) $t['capacity'];
            }

            // Ambil harga termurah dari varian tiket untuk dijadikan harga dasar event
            $minPrice = 0;
            if ($request->has('tickets') && is_array($request->tickets) && count($request->tickets) > 0) {
                $minPrice = collect($request->tickets)->min('price') ?? 0;
            }

            // Update pengaturan Harga & Form di tabel Event
            $event->update([
                'is_free_event'         => $request->is_free_event,
                'is_ppn_included'       => $request->is_ppn_included,
                'local_tax_percentage'  => $request->local_tax_percentage ?? 0, 
                'max_tickets_per_order' => $request->max_tickets_per_order ?? 5, 
                'total_seats'           => $totalCapacity,
                'available_seats'       => $totalCapacity, 
                'custom_fields'         => $request->custom_fields ?? [],
                'price'                 => $request->is_free_event ? 0 : $minPrice,
            ]);

            // ================= SISTEM UPDATE/CREATE CERDAS =================
            $existingTierIds = TicketTier::where('event_id', $event->id)->pluck('id')->toArray();
            $keptTierIds = [];
            $tierIdMap = []; // Menyimpan mapping ID sementara dari frontend ke UUID asli di DB

            // PASS 1: Simpan atau Perbarui semua Tiket
            foreach ($request->tickets as $index => $t) {
                if (empty($t['name']) && $isManual) continue; 
                
                $tierData = [
                    'event_id'                => $event->id,
                    'name'                    => $t['name'] ?: 'Draft Ticket',
                    'price'                   => $request->is_free_event ? 0 : $t['price'],
                    'capacity'                => $t['capacity'],
                    
                    // Inject Advanced Rules
                    'close_type'              => $t['close_type'] ?? 'quota',
                    'start_sales_at'          => !empty($t['start_sales_at']) ? $t['start_sales_at'] : null,
                    'end_sales_at'            => !empty($t['end_sales_at']) ? $t['end_sales_at'] : null,
                    'is_visible_before_sales' => isset($t['is_visible_before_sales']) ? $t['is_visible_before_sales'] : true,
                    'is_active'               => isset($t['is_active']) ? $t['is_active'] : true,
                ];

                if (isset($t['id']) && in_array($t['id'], $existingTierIds)) {
                    // Update tiket lama (Jangan hapus data sisa kursinya secara membabi buta)
                    $tier = TicketTier::find($t['id']);
                    $capacityDiff = $t['capacity'] - $tier->capacity;
                    $tierData['available_seats'] = max(0, $tier->available_seats + $capacityDiff); 
                    
                    $tier->update($tierData);
                    $keptTierIds[] = $tier->id;
                    $tierIdMap[$t['id']] = $tier->id; // Mapping ID Lama
                } else {
                    // Buat tiket baru
                    $tierData['available_seats'] = $t['capacity'];
                    $tier = TicketTier::create($tierData);
                    
                    $keptTierIds[] = $tier->id;
                    // Mapping ID Sementara (misal: 'temp_0') ke ID UUID asli yang baru terbuat
                    $frontendId = $t['id'] ?? 'temp_' . $index;
                    $tierIdMap[$frontendId] = $tier->id; 
                }
            }

            // Hapus tiket yang di-delete oleh user dari form frontend
            $tiersToDelete = array_diff($existingTierIds, $keptTierIds);
            TicketTier::whereIn('id', $tiersToDelete)->delete();

            // PASS 2: Relasikan "Buka Otomatis setelah Tiket X"
            // Kita jalankan Pass 2 agar jika tiket pemicunya baru dibuat di Pass 1, kita bisa pakai UUID barunya.
            foreach ($request->tickets as $index => $t) {
                $frontendId = $t['id'] ?? 'temp_' . $index;
                $actualDbId = $tierIdMap[$frontendId] ?? null;

                if ($actualDbId) {
                    if (!empty($t['auto_open_after_tier_id'])) {
                        // Ambil ID Asli dari peta Mapping
                        $targetDbId = $tierIdMap[$t['auto_open_after_tier_id']] ?? $t['auto_open_after_tier_id'];
                        TicketTier::where('id', $actualDbId)->update(['auto_open_after_tier_id' => $targetDbId]);
                    } else {
                        TicketTier::where('id', $actualDbId)->update(['auto_open_after_tier_id' => null]);
                    }
                }
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Data tiket berhasil disimpan.']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Kesalahan internal.', 'error_detail' => $e->getMessage()], 500);
        }
    }

    // ================= FUNGSI MANAJEMEN TIKET ORGANIZER =================

    public function openTicketNow(Request $request, $ticketTierId)
    {
        $userId = $request->user()->id;
        
        $tier = TicketTier::where('id', $ticketTierId)
            ->whereHas('event', function($q) use ($userId) {
                $q->where('organizer_id', $userId);
            })->firstOrFail();

        // Paksa buka sekarang: Set start_sales_at ke waktu saat ini, dan hapus pemicu auto-open jika ada
        $tier->update([
            'start_sales_at' => now(),
            'auto_open_after_tier_id' => null
        ]);

        return response()->json(['success' => true, 'message' => 'Penjualan tiket berhasil dibuka sekarang.']);
    }

    public function extendTicket(Request $request, $ticketTierId)
    {
        $request->validate([
            'additional_capacity' => 'required|integer|min:0',
            'new_end_date' => 'nullable|date|after_or_equal:now',
        ]);

        $userId = $request->user()->id;
        
        $tier = TicketTier::where('id', $ticketTierId)
            ->whereHas('event', function($q) use ($userId) {
                $q->where('organizer_id', $userId);
            })->firstOrFail();

        $dataToUpdate = [];

        // Jika EO menambah kapasitas
        if ($request->additional_capacity > 0) {
            $dataToUpdate['capacity'] = $tier->capacity + $request->additional_capacity;
            $dataToUpdate['available_seats'] = $tier->available_seats + $request->additional_capacity;
            
            // Kita juga harus mengupdate total_seats dan available_seats di tabel Event
            $event = $tier->event;
            $event->update([
                'total_seats' => $event->total_seats + $request->additional_capacity,
                'available_seats' => $event->available_seats + $request->additional_capacity,
            ]);
        }

        // Jika EO mengubah tanggal akhir (hanya berlaku jika tipe tutupnya date/both)
        if ($request->has('new_end_date')) {
            $dataToUpdate['end_sales_at'] = $request->new_end_date;
        }

        if (!empty($dataToUpdate)) {
            $tier->update($dataToUpdate);
        }

        return response()->json(['success' => true, 'message' => 'Tiket berhasil diperbarui.']);
    }

    public function requestPublish($id)
    {
        $event = \App\Models\Event::where('id', $id)
                    ->where('organizer_id', request()->user()->id)
                    ->firstOrFail();

        $event->update(['status' => 'pending']); // Mengubah dari draft menjadi pending approval admin

        return response()->json([
            'success' => true,
            'message' => 'Event berhasil diajukan dan menunggu persetujuan Admin.'
        ]);
    }
}