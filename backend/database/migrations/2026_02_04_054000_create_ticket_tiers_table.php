<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Buat tabel dan kolomnya terlebih dahulu
        Schema::create('ticket_tiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained()->cascadeOnDelete();
            
            $table->string('name');
            $table->string('type')->default('paid'); // free, paid, donation
            $table->decimal('price', 12, 2)->default(0);
            $table->integer('capacity');
            $table->integer('available_seats');
            $table->text('description')->nullable();
            
            // Advanced Rules & Ticketing Logic
            $table->enum('close_type', ['quota', 'date', 'both'])->default('quota');
            $table->dateTime('start_sales_at')->nullable();
            $table->dateTime('end_sales_at')->nullable();
            $table->boolean('is_visible_before_sales')->default(true);
            $table->boolean('is_active')->default(true);
            
            // Kolom Self-referencing (Tanpa constrained dulu)
            $table->uuid('auto_open_after_tier_id')->nullable();
            
            $table->timestamps();
        });

        // 2. Tambahkan Foreign Key Constraint SETELAH tabel selesai dibuat
        Schema::table('ticket_tiers', function (Blueprint $table) {
            $table->foreign('auto_open_after_tier_id')
                  ->references('id')
                  ->on('ticket_tiers')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_tiers');
    }
};