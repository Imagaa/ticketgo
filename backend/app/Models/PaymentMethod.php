<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = [
        'name',
        'payment_type',
        'bank',
        'flat_fee',
        'percent_fee',
        'is_active',
        'icon_url'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'flat_fee' => 'decimal:2',
        'percent_fee' => 'decimal:2',
    ];
}