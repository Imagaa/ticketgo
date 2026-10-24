<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'event_id',
        'quantity',
        'price_per_ticket',
        'subtotal',
        'admin_fee',
        'total',
        'payment_method',
        'payment_status',
        'payment_token',
        'payment_url',
        'payment_details',
        'paid_at',
        'expired_at',
        'status',
        'cancellation_reason',
        'order_code',
        'ticket_details',
        'custom_fields_responses',
        'affiliator_id', 
        'affiliate_commission_amount', 
        'is_affiliate_commission_cashed_out',
        'ticket_tier_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price_per_ticket' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'admin_fee' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_at' => 'datetime',
        'expired_at' => 'datetime',
        'ticket_details' => 'array',
        'custom_fields_responses' => 'array',
        'payment_details' => 'array',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class)->withTrashed();
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    // Scopes
    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    // Helper Methods
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending_payment', 'confirmed']) 
            && $this->event->start_date > now();
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($order) {
            if (empty($order->order_code)) {
                $order->order_code = 'ORD-' . strtoupper(substr($order->id ?? uniqid(), 0, 8));
            }
            $order->expired_at ??= now()->addHour();
        });
    }

    public function affiliator() 
    {
        return $this->belongsTo(User::class, 'affiliator_id');
    }
}
