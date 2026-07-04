<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ewaste_items', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('ai_class_name')->unique();

            $table->string('category_name')->nullable();

            $table->decimal('avg_weight_kg', 10, 2)->default(0);
            $table->decimal('min_weight_kg', 10, 2)->nullable();
            $table->decimal('max_weight_kg', 10, 2)->nullable();

            $table->decimal('price_per_kg', 12, 2)->nullable();
            $table->decimal('price_per_item', 12, 2)->nullable();

            $table->boolean('is_batch')->default(false);
            $table->boolean('is_hazardous')->default(false);
            $table->boolean('requires_staff_verification')->default(true);

            $table->string('status')->default('active');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['ai_class_name', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ewaste_items');
    }
};
