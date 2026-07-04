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
        Schema::create('waste_offers', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Waste Listing Relationship
            |--------------------------------------------------------------------------
            |
            | The waste item/listing where price negotiation is happening.
            |
            */

            $table->foreignId('waste_listing_id')
                ->constrained('waste_listings')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Offer Creator
            |--------------------------------------------------------------------------
            |
            | offered_by can be:
            | - institution user
            | - enviroserve staff
            | - admin
            |
            */

            $table->foreignId('offered_by')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Offer Receiver
            |--------------------------------------------------------------------------
            |
            | Optional user who receives the offer.
            |
            */

            $table->foreignId('offered_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Offer Amount
            |--------------------------------------------------------------------------
            */

            $table->decimal('offer_amount', 12, 2);

            $table->string('currency')->default('RWF');

            /*
            |--------------------------------------------------------------------------
            | Offer Type
            |--------------------------------------------------------------------------
            |
            | initial_offer = first price offer
            | counter_offer = another price from the other side
            | final_offer   = last agreed offer
            |
            */

            $table->enum('offer_type', [
                'initial_offer',
                'counter_offer',
                'final_offer',
            ])->default('initial_offer');

            /*
            |--------------------------------------------------------------------------
            | Offer Status
            |--------------------------------------------------------------------------
            */

            $table->enum('status', [
                'pending',
                'accepted',
                'rejected',
                'cancelled',
                'expired',
            ])->default('pending');

            /*
            |--------------------------------------------------------------------------
            | Response Information
            |--------------------------------------------------------------------------
            */

            $table->foreignId('responded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('responded_at')->nullable();

            $table->text('message')->nullable();

            $table->text('response_note')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Expiry Time
            |--------------------------------------------------------------------------
            |
            | Optional: offer can expire if institution does not respond.
            |
            */

            $table->timestamp('expires_at')->nullable();

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
            $table->index('offered_by');
            $table->index('offered_to');
            $table->index('offer_type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waste_offers');
    }
};