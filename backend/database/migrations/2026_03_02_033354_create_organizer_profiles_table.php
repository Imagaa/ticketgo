<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            $table->string('company_name');
            $table->string('legal_name')->nullable();
            $table->string('tax_id')->nullable();
            
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            
            $table->string('bank_name')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number')->nullable();
            
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            
            // Link Finance & Magic Link
            $table->string('magic_link_token')->nullable()->unique();
            $table->decimal('custom_platform_fee', 5, 2)->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizer_profiles');
    }
};