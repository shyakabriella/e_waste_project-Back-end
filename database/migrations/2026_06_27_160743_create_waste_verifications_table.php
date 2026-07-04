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
        Schema::create('waste_verifications', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Waste Listing Relationship
            |--------------------------------------------------------------------------
            |
            | The waste item/listing being verified.
            |
            */

            $table->foreignId('waste_listing_id')
                ->constrained('waste_listings')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Staff Who Verified
            |--------------------------------------------------------------------------
            |
            | Enviroserve staff/admin who checks the waste physically.
            |
            */

            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | AI Analysis Reference
            |--------------------------------------------------------------------------
            |
            | Optional: if this verification is based on an AI analysis result.
            |
            */

            $table->foreignId('waste_ai_analysis_id')
                ->nullable()
                ->constrained('waste_ai_analyses')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Category Confirmation
            |--------------------------------------------------------------------------
            |
            | Staff confirms whether AI/client category was correct.
            |
            */

            $table->foreignId('confirmed_category_id')
                ->nullable()
                ->constrained('waste_categories')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Weight Verification
            |--------------------------------------------------------------------------
            |
            | client_estimated_weight_kg = weight entered by institution/client
            | ai_estimated_weight_kg     = weight suggested by AI/system
            | verified_weight_kg         = real final kg confirmed by staff
            |
            */

            $table->decimal('client_estimated_weight_kg', 10, 2)->nullable();
            $table->decimal('ai_estimated_weight_kg', 10, 2)->nullable();
            $table->decimal('verified_weight_kg', 10, 2);

            /*
            |--------------------------------------------------------------------------
            | Quantity Verification
            |--------------------------------------------------------------------------
            */

            $table->unsignedInteger('client_quantity')->nullable();
            $table->unsignedInteger('verified_quantity')->default(1);

            /*
            |--------------------------------------------------------------------------
            | Waste Condition
            |--------------------------------------------------------------------------
            */

            $table->enum('condition_status', [
                'good',
                'damaged',
                'partially_damaged',
                'hazardous',
                'not_e_waste',
                'not_accepted',
            ])->default('damaged');

            $table->boolean('is_accepted')->default(true);

            $table->boolean('is_hazardous')->default(false);

            /*
            |--------------------------------------------------------------------------
            | Verification Decision
            |--------------------------------------------------------------------------
            |
            | pending   = waiting for verification
            | approved  = accepted by staff
            | rejected  = not accepted
            | corrected = AI/client info was corrected by staff
            |
            */

            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'corrected',
            ])->default('pending');

            /*
            |--------------------------------------------------------------------------
            | Pricing After Verification
            |--------------------------------------------------------------------------
            |
            | Staff can confirm final price after checking real kg.
            |
            */

            $table->decimal('price_per_kg', 12, 2)->nullable();
            $table->decimal('price_per_item', 12, 2)->nullable();
            $table->decimal('verified_total_price', 12, 2)->nullable();
            $table->string('currency')->default('RWF');

            /*
            |--------------------------------------------------------------------------
            | Notes
            |--------------------------------------------------------------------------
            */

            $table->text('verification_notes')->nullable();
            $table->text('rejection_reason')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Verification Time
            |--------------------------------------------------------------------------
            */

            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index('waste_listing_id');
            $table->index('verified_by');
            $table->index('waste_ai_analysis_id');
            $table->index('confirmed_category_id');
            $table->index('condition_status');
            $table->index('is_accepted');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waste_verifications');
    }
};