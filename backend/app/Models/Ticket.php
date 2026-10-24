<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

protected $fillable = [
        'order_id',
        'event_id',
        'user_id',
        'ticket_code',
        'attendee_name',
        'attendee_email',
        'qr_code_data',
        'qr_code_path',
        'status',
        'validated_at',
        'validated_by',
        'last_downloaded_at',
        'sales_start_date',
        'sales_end_date',
        'ticket_tier_id',
    ];

    protected $casts = [
        'validated_at' => 'datetime',
        'last_downloaded_at' => 'datetime',
        'attendee_answers' => 'array',
        
        // --- Tambahan Baru ---
        'sales_start_date' => 'datetime',
        'sales_end_date' => 'datetime',
    ];

    // Relationships
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function ticketTier()
    {
        return $this->belongsTo(TicketTier::class, 'ticket_tier_id', 'id');
    }

    // Scopes
    public function scopeValid($query)
    {
        return $query->where('status', 'valid');
    }

    public function scopeUsed($query)
    {
        return $query->where('status', 'used');
    }

    // Helper Methods
    public function isValid(): bool
    {
        return $this->status === 'valid';
    }

    public function isUsed(): bool
    {
        return $this->status === 'used';
    }

    public function canBeValidated(): bool
    {
        return $this->status === 'valid' 
            && $this->event->start_date <= now()
            && $this->event->end_date >= now();
    }

    /**
     * Generate QR code for this ticket (SVG format)
     * Data is immutable - always returns same QR for same ticket
     */
    public function generateQrCode(): string
    {
        $data = json_encode([
            'ticket_id' => $this->id,
            'ticket_code' => $this->ticket_code,
            'event_id' => $this->event_id,
            'user_id' => $this->user_id,
            'generated_at' => $this->created_at->toISOString(),
        ]);

        // Generate QR as SVG (works without ext-gd)
        return \SimpleSoftwareIO\QrCode\Facades\QrCode::size(300)
            ->margin(1)
            ->generate($data);
    }

    /**
     * Get QR code data payload (for scanner validation)
     */
    public function getQrData(): array
    {
        return [
            'ticket_id' => $this->id,
            'ticket_code' => $this->ticket_code,
            'event_id' => $this->event_id,
            'user_id' => $this->user_id,
            'generated_at' => $this->created_at->toISOString(),
        ];
    }

    public static function validateQrData(string $qrData): ?self
    {
        try {
            $data = json_decode($qrData, true);
            
            if (!isset($data['ticket_id']) || !isset($data['ticket_code'])) {
                return null;
            }

            // Find ticket and verify data integrity
            $ticket = self::find($data['ticket_id']);
            
            if (!$ticket || $ticket->ticket_code !== $data['ticket_code']) {
                return null;
            }

            return $ticket;
        } catch (\Exception $e) {
            return null;
        }
    }
}

//redeploy railway