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
        Schema::create('waste_listings', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Owner / Institution
            |--------------------------------------------------------------------------
            |
            | The institution/client who uploads the waste.
            |
            */

            $table->foreignId('institution_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Waste Category
            |--------------------------------------------------------------------------
            |
            | Example categories:
            | Keyboard, Monitor, Laptop, Phone, Printer, Battery, Server.
            |
            */

            $table->foreignId('waste_category_id')
                ->nullable()
                ->constrained('waste_categories')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Waste Basic Information
            |--------------------------------------------------------------------------
            */

            $table->string('title');
            // Example: Old HP Laptop, Broken Keyboard, Used Monitor

            $table->text('description')->nullable();

            $table->unsignedInteger('quantity')->default(1);

            /*
            |--------------------------------------------------------------------------
            | Weight Information
            |--------------------------------------------------------------------------
            |
            | estimated_weight_kg     = entered by institution or calculated by system
            | ai_estimated_weight_kg  = estimated by AI/system from detected item
            | verified_weight_kg      = final real weight confirmed by Enviroserve staff
            |
            */

            $table->decimal('estimated_weight_kg', 10, 2)->nullable();
            $table->decimal('ai_estimated_weight_kg', 10, 2)->nullable();
            $table->decimal('verified_weight_kg', 10, 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | AI Image Detection Result
            |--------------------------------------------------------------------------
            |
            | AI can detect if image is Keyboard, Screen, Laptop, etc.
            | Then system estimates kilogram using waste category average weight.
            |
            */

            $table->string('ai_detected_item')->nullable();
            $table->string('ai_detected_category')->nullable();

            $table->enum('ai_waste_nature', ['ibibora', 'ibitabora'])
                ->nullable();

            $table->boolean('ai_is_e_waste')->default(false);

            $table->decimal('ai_confidence', 5, 2)->nullable();
            // Example: 92.50 means AI is 92.5% confident

            $table->longText('ai_analysis_note')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Pricing
            |--------------------------------------------------------------------------
            |
            | expected_price = price entered by institution
            | final_price    = final agreed price after verification/negotiation
            |
            */

            $table->decimal('expected_price', 12, 2)->nullable();
            $table->decimal('final_price', 12, 2)->nullable();

            $table->string('currency')->default('RWF');

            /*
            |--------------------------------------------------------------------------
            | Pickup Location
            |--------------------------------------------------------------------------
            */

            $table->text('pickup_address')->nullable();

            $table->string('district')->nullable();
            $table->string('sector')->nullable();
            $table->string('cell')->nullable();
            $table->string('village')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Listing Status
            |--------------------------------------------------------------------------
            |
            | pending          = uploaded but not checked yet
            | ai_analyzed      = AI has analyzed image
            | verified         = Enviroserve staff verified waste
            | offer_sent       = recycler/admin sent offer
            | offer_accepted   = institution accepted offer
            | pickup_scheduled = pickup date/time assigned
            | collected        = waste collected
            | completed        = transaction completed
            | rejected         = waste rejected
            | cancelled        = request cancelled
            |
            */

            $table->enum('status', [
                'pending',
                'ai_analyzed',
                'verified',
                'offer_sent',
                'offer_accepted',
                'pickup_scheduled',
                'collected',
                'completed',
                'rejected',
                'cancelled',
            ])->default('pending');

            /*
            |--------------------------------------------------------------------------
            | Staff Verification Control
            |--------------------------------------------------------------------------
            */

            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('verified_at')->nullable();

            $table->text('verification_notes')->nullable();

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

            $table->index('institution_id');
            $table->index('waste_category_id');
            $table->index('status');
            $table->index('district');
            $table->index('sector');
            $table->index('ai_detected_item');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waste_listings');
    }
};