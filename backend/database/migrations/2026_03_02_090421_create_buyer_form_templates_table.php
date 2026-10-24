<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_form_templates', function (Blueprint $table) {
            $table->id();
            $table->string('field_name');
            $table->string('field_type'); // text, number, select, date, etc.
            $table->json('options')->nullable(); // For select/radio options
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_form_templates');
    }
};