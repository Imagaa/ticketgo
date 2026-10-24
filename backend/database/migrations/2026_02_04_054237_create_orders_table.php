<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('event_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ticket_tier_id')->nullable()->constrained('ticket_tiers')->nullOnDelete();

            $table->string('order_code')->unique();
            $table->integer('quantity');
            $table->decimal('price_per_ticket', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('admin_fee', 12, 2)->default(0);
            $table->decimal('total', 12, 2);

            // Status
            $table->enum('status', ['pending_payment', 'confirmed', 'cancelled', 'failed', 'refunded'])->default('pending_payment');
            $table->string('payment_status')->default('pending');
            $table->string('payment_method')->nullable();
            $table->string('payment_token')->nullable();       // ← TAMBAH: Midtrans snap token
            $table->string('payment_url')->nullable();
            $table->json('payment_details')->nullable();       // ← TAMBAH: raw response Midtrans

            // Timestamps transaksi
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();       // ← deadline bayar

            // Pembatalan
            $table->text('cancellation_reason')->nullable();

            // Dynamic data
            $table->json('custom_fields_responses')->nullable();
            $table->json('ticket_details')->nullable();

            // Tracking Affiliate
            $table->foreignId('affiliator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('affiliate_commission_amount', 12, 2)->default(0);
            $table->boolean('is_affiliate_commission_cashed_out')->default(false);

            $table->softDeletes();                             // ← TAMBAH: deleted_at (Order model pakai SoftDeletes)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
