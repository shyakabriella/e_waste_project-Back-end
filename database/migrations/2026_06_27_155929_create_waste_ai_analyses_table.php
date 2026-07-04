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
        Schema::create('waste_ai_analyses', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Relationships
            |--------------------------------------------------------------------------
            */

            $table->foreignId('waste_listing_id')
                ->constrained('waste_listings')
                ->cascadeOnDelete();

            $table->foreignId('waste_photo_id')
                ->nullable()
                ->constrained('waste_photos')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | AI Provider Information
            |--------------------------------------------------------------------------
            |
            | Example providers:
            | openai, gemini, local_model
            |
            */

            $table->string('ai_provider')->nullable();
            // Example: openai, gemini

            $table->string('ai_model')->nullable();
            // Example: gpt-4o-mini, gemini-1.5-flash

            /*
            |--------------------------------------------------------------------------
            | AI Detection Result
            |--------------------------------------------------------------------------
            */

            $table->string('detected_item')->nullable();
            // Example: Keyboard, Monitor, Laptop, Phone, Battery

            $table->foreignId('detected_category_id')
                ->nullable()
                ->constrained('waste_categories')
                ->nullOnDelete();

            $table->string('detected_category_name')->nullable();
            // Example: Keyboard, Monitor

            $table->enum('waste_nature', ['ibibora', 'ibitabora'])
                ->nullable();

            $table->boolean('is_e_waste')->default(false);

            $table->boolean('is_hazardous')->default(false);

            /*
            |--------------------------------------------------------------------------
            | Quantity and Weight Estimation
            |--------------------------------------------------------------------------
            |
            | AI can detect item type.
            | Then system can estimate kg using average weight from waste_categories.
            |
            */

            $table->unsignedInteger('quantity_detected')->nullable();

            $table->decimal('estimated_weight_kg', 10, 2)->nullable();

            $table->decimal('min_estimated_weight_kg', 10, 2)->nullable();

            $table->decimal('max_estimated_weight_kg', 10, 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | AI Confidence and Explanation
            |--------------------------------------------------------------------------
            */

            $table->decimal('confidence', 5, 2)->nullable();
            // Example: 92.50 means 92.5%

            $table->longText('analysis_note')->nullable();
            // Short explanation from AI

            $table->json('analysis_result')->nullable();
            // Full structured AI JSON response

            /*
            |--------------------------------------------------------------------------
            | AI Processing Status
            |--------------------------------------------------------------------------
            */

            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'failed',
                'verified',
                'rejected',
            ])->default('pending');

            $table->text('error_message')->nullable();

            $table->timestamp('analyzed_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Staff Verification
            |--------------------------------------------------------------------------
            |
            | AI result is only suggestion.
            | Enviroserve staff must verify the real waste and real kg.
            |
            */

            $table->boolean('verified_by_staff')->default(false);

            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('verified_at')->nullable();

            $table->foreignId('staff_corrected_category_id')
                ->nullable()
                ->constrained('waste_categories')
                ->nullOnDelete();

            $table->decimal('staff_corrected_weight_kg', 10, 2)->nullable();

            $table->text('staff_feedback')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Timestamps
            |--------------------------------------------------------------------------
            */

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index('waste_listing_id');
            $table->index('waste_photo_id');
            $table->index('detected_category_id');
            $table->index('waste_nature');
            $table->index('is_e_waste');
            $table->index('is_hazardous');
            $table->index('status');
            $table->index('verified_by_staff');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waste_ai_analyses');
    }
};