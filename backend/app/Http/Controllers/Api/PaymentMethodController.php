<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function index()
    {
        // Hanya kirim metode pembayaran yang diaktifkan (is_active = true) oleh Admin
        $methods = PaymentMethod::where('is_active', true)->get();
        return response()->json($methods);
    }
}