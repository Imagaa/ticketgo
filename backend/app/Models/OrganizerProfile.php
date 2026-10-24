<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizerProfile extends Model
{
    use HasFactory;

    // Pastikan semua kolom baru ditambahkan ke fillable agar bisa disimpan!
    protected $fillable = [
        'user_id', 'pic_name', 'ktp_number', 'address', 'city', 
        'display_name', 'logo_url', 'bank_name', 'bank_account_number', 
        'bank_account_name', 'is_verified',
        
        // --- TAMBAHAN KOLOM MAGIC LINK ---
        'magic_link_token',
        'magic_link_pin',
        'magic_link_expires_at',
        'magic_link_failed_attempts',
        'magic_link_locked_until'
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'magic_link_expires_at' => 'datetime',
        'magic_link_locked_until' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}