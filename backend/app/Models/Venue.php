<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'city',
        'province',
        'postal_code',
        'latitude',
        'longitude',
        'capacity',
        'facilities',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'capacity' => 'integer',
        'facilities' => 'array', // Auto JSON encode/decode
        'is_active' => 'boolean',
    ];

    // Relationship: Venue has many Events
    public function events()
    {
        return $this->hasMany(Event::class);
    }
}
