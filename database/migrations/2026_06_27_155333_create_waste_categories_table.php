<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('waste_categories', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Basic Category Information
            |--------------------------------------------------------------------------
            */

            $table->string('name'); 
            // Example: Keyboard, Laptop, Monitor, Phone, Printer, Battery

            $table->string('slug')->unique(); 
            // Example: keyboard, laptop, monitor

            $table->text('description')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Waste Classification
            |--------------------------------------------------------------------------
            |
            | ibibora    = biodegradable waste
            | ibitabora  = non-biodegradable waste
            |
            | For this project, most e-waste will be ibitabora.
            |
            */

            $table->enum('waste_nature', ['ibibora', 'ibitabora'])
                ->default('ibitabora');

            $table->boolean('is_e_waste')->default(true);
            // true = electronic waste

            $table->boolean('is_hazardous')->default(false);
            // true for dangerous items like batteries

            /*
            |--------------------------------------------------------------------------
            | Weight Estimation
            |--------------------------------------------------------------------------
            |
            | These fields help the system estimate kilogram.
            | Example:
            | Keyboard average = 0.80 kg
            | Quantity = 3
            | Estimated weight = 2.40 kg
            |
            */

            $table->decimal('average_weight_kg', 10, 2)->nullable();
            $table->decimal('min_weight_kg', 10, 2)->nullable();
            $table->decimal('max_weight_kg', 10, 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Pricing
            |--------------------------------------------------------------------------
            |
            | Admin can set price per kg or per item.
            |
            */

            $table->decimal('price_per_kg', 12, 2)->default(0);
            $table->decimal('price_per_item', 12, 2)->default(0);
            $table->string('currency')->default('RWF');

            /*
            |--------------------------------------------------------------------------
            | System Control
            |--------------------------------------------------------------------------
            */

            $table->enum('status', ['active', 'inactive'])->default('active');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index('name');
            $table->index('waste_nature');
            $table->index('is_e_waste');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waste_categories');
    }
};