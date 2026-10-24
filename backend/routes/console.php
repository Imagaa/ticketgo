<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Ticket;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cron Job: Menghanguskan tiket H+1 Acara
Schedule::call(function () {
    // Cari semua tiket valid yang tanggal event-nya sudah lewat dari hari ini
    $expiredCount = Ticket::where('status', 'valid')
        ->whereHas('event', function ($query) {
            $query->whereDate('end_date', '<', now()->toDateString());
        })
        ->update(['status' => 'used']); 
        // Catatan: Saya set menjadi 'used' agar langsung masuk ke tab histori di frontend, 
        // dan menghindari error jika kolom DB status tidak menerima string 'expired'.
        
})->dailyAt('00:00')->name('tickets:auto-expire');