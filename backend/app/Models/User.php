<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    // Kunci guard ke 'api' agar sinkron dengan Spatie Permission + Sanctum
    protected string $guard_name = 'api';

    protected $fillable = [
        'name',
        'nickname',
        'email',
        'password',
        'phone',
        'address',
        'bio',
        'avatar_path',
        'date_of_birth',
        'is_active',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'date_of_birth'     => 'date',
            'is_active'         => 'boolean',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────

    public function events()
    {
        return $this->hasMany(Event::class, 'organizer_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function organizerProfile()
    {
        return $this->hasOne(OrganizerProfile::class);
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────

    /**
     * Nama tampilan: pakai nickname jika ada, fallback ke name
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->nickname ?? $this->name;
    }

    /**
     * Hitung umur dari date_of_birth
     */
    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth
            ? $this->date_of_birth->age
            : null;
    }
}
