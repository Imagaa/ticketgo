<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'title',
        'slug',
        'description',
        'category_id',
        'venue_id',
        'venue_name',
        'venue_address',
        'gmaps_url',
        'social_links',
        'start_date',
        'end_date',
        'sales_start_date',
        'sales_end_date',
        'event_end_date',
        'price',
        'total_seats',        
        'available_seats',    
        'capacity',
        'poster_path',
        'seatmap_path',
        'status',
        'is_free_event',
        'is_ppn_included',
        'admin_fee_percentage',
        'ppn_percentage',
        'organizer_id',
        'max_tickets_per_order',
        'local_tax_percentage',
        'custom_fields',
        'delete_requested_at',
        'artist_id', 
        'is_affiliate_enabled', 
        'affiliate_activation_status', 
        'affiliate_commission_rate',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'published_at' => 'datetime',
        'price' => 'decimal:2',
        'is_free' => 'boolean',
        'is_featured' => 'boolean',
        'total_seats' => 'integer',
        'available_seats' => 'integer',
        'custom_fields' => 'array',
        'social_links' => 'array',
        'is_free_event' => 'boolean',
        'is_ppn_included' => 'boolean',
        'sales_start_date' => 'datetime',
        'sales_end_date' => 'datetime',
        'event_end_date' => 'datetime',
        'custom_fields'   => 'array',
        'max_tickets_per_order' => 'integer',
        'local_tax_percentage' => 'decimal:2',
        'custom_fields' => 'array',
        'delete_requested_at' => 'datetime',
    ];

    // Use UUID in routes (instead of integer id)
    public function getRouteKeyName()
    {
        return 'id'; // UUID is already the primary key
    }

    // Relationships
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    public function organizer()
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('end_date', '>=', now());
    }

        // Relationship: Event has many Orders
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // Relationship: Event has many Tickets
    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function ticketTiers()
    {
        return $this->hasMany(TicketTier::class);
    }

    public function artist() 
    {
        return $this->belongsTo(User::class, 'artist_id');
    }
}
