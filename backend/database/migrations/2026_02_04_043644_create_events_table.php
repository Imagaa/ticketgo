<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Tanggal event & penjualan
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->dateTime('event_end_date')->nullable(); // ← TAMBAH: alias end_date, dipakai wizard
            $table->dateTime('sales_start_date')->nullable();
            $table->dateTime('sales_end_date')->nullable();

            // Venue
            $table->string('venue_name')->nullable();
            $table->text('venue_address')->nullable();
            $table->string('gmaps_url')->nullable();

            // Media
            $table->string('poster_path')->nullable();
            $table->string('seatmap_path')->nullable();

            // Status & Checkout Settings
            $table->enum('status', ['draft', 'pending', 'published', 'rejected', 'cancelled'])->default('draft');
            $table->boolean('is_free_event')->default(false);
            $table->boolean('is_featured')->default(false);

            // Pajak & Fee
            $table->boolean('is_ppn_included')->default(false);
            $table->decimal('ppn_percentage', 5, 2)->default(0);     // ← TAMBAH: PPN 11% dsb
            $table->decimal('admin_fee_percentage', 5, 2)->default(5.00);
            $table->decimal('local_tax_percentage', 5, 2)->default(0);

            $table->integer('max_tickets_per_order')->default(5);

            // Inventory Baseline
            $table->integer('total_seats')->default(0);
            $table->integer('available_seats')->default(0);
            $table->decimal('price', 12, 2)->default(0);

            // JSON Fields (Dynamic)
            $table->json('custom_fields')->nullable();
            $table->json('social_links')->nullable();

            // Multi-Tenant (Artist & Affiliate)
            $table->foreignId('artist_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_affiliate_enabled')->default(false);
            $table->enum('affiliate_activation_status', ['unpaid', 'paid', 'postpaid'])->default('unpaid');
            $table->decimal('affiliate_commission_rate', 5, 2)->default(0);

            // Soft Delete & Timestamps
            $table->timestamp('delete_requested_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
