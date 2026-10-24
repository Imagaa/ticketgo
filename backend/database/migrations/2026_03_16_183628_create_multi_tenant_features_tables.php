<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel Wallet (Satu user punya 1 dompet)
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestamps();
        });

        // 2. Tabel Mutasi (Buku tabungan / Log keluar masuk uang)
        Schema::create('wallet_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->enum('type', ['credit', 'debit']); // credit = uang masuk, debit = uang keluar
            $table->string('description');
            $table->timestamps();
        });

        // 3. Tabel Guest Lists (F&F - Placeholder)
        Schema::create('guest_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('ticket_tier_name')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });

        // 4. Tabel Technical Riders (Placeholder)
        Schema::create('technical_riders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('file_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_riders');
        Schema::dropIfExists('guest_lists');
        Schema::dropIfExists('wallet_mutations');
        Schema::dropIfExists('wallets');
    }
};
