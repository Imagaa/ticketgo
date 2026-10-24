<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids; // Tambahkan ini

class TicketTier extends Model
{
    use HasFactory, HasUuids; // Tambahkan HasUuids di sini

    protected $fillable = [
        'event_id',
        'name',
        'description',
        'price',
        'capacity',
        'available_seats',
        'max_per_user',
        
        // --- Advanced Rules ---
        'close_type',                 // 'quota', 'date', 'both'
        'start_sales_at',             // Kapan mulai dijual
        'end_sales_at',               // Kapan otomatis ditutup (jika close_type 'date' / 'both')
        'auto_open_after_tier_id',    // ID tier lain yang jadi pemicu buka otomatis
        'is_visible_before_sales',    // Boleh di-spill atau tidak
        'is_active'                   // Status aktif manual
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'start_sales_at' => 'datetime',
        'end_sales_at' => 'datetime',
        'is_visible_before_sales' => 'boolean',
        'is_active' => 'boolean'
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    // Relasi untuk mengetahui tier mana yang menjadi pemicu tier ini
    public function autoOpenAfterTier()
    {
        return $this->belongsTo(TicketTier::class, 'auto_open_after_tier_id');
    }

    // --- HELPER UNTUK CEK STATUS PENJUALAN SAAT INI (REAL-TIME) ---
    public function getCurrentStatusAttribute()
{
    if (!$this->is_active) return 'inactive';

    if (in_array($this->close_type, ['quota', 'both']) && $this->available_seats <= 0) {
        return 'sold_out';
    }

    $now = now();

    if (in_array($this->close_type, ['date', 'both']) && $this->end_sales_at && $now->isAfter($this->end_sales_at)) {
        return 'ended';
    }

    if ($this->start_sales_at && $now->isBefore($this->start_sales_at) && !$this->auto_open_after_tier_id) {
        return 'coming_soon';
    }

    if ($this->auto_open_after_tier_id) {
        // Gunakan query langsung TANPA load model penuh (hindari trigger $appends)
        $triggerTier = TicketTier::withoutGlobalScopes()
            ->select('close_type', 'available_seats', 'end_sales_at', 'is_active')
            ->find($this->auto_open_after_tier_id);

        if ($triggerTier) {
            // Cek manual TANPA memanggil ->current_status
            $triggerSoldOut = in_array($triggerTier->close_type, ['quota', 'both']) 
                && $triggerTier->available_seats <= 0;
            $triggerEnded = in_array($triggerTier->close_type, ['date', 'both']) 
                && $triggerTier->end_sales_at 
                && now()->isAfter($triggerTier->end_sales_at);

            if (!$triggerSoldOut && !$triggerEnded) {
                return 'waiting_other_tier';
            }
        }
    }

    return 'on_sale';
}


    // Tambahkan properti buatan ini agar selalu terkirim ke Frontend
    protected $appends = ['current_status'];
}