<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BuyerFormTemplate extends Model
{
    use HasFactory; // (Boleh ada, boleh tidak)

    // ✅ PASTIKAN INI BENAR: field_name dan field_type
    protected $fillable = [
        'field_name', 
        'field_type', 
        'options', 
        'is_active'
    ];

    protected $casts = [
        'options' => 'array',
        'is_active' => 'boolean',
    ];
}