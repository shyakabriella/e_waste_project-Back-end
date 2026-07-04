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
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Waste Listing Relationship
            |--------------------------------------------------------------------------
            |
            | The completed waste listing/deal where commission is calculated.
            |
            */

            $table->foreignId('waste_listing_id')
                ->constrained('waste_listings')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Pickup Relationship
            |--------------------------------------------------------------------------
            |
            | Optional: commission can be linked to the completed pickup.
            |
            */

            $table->foreignId('pickup_id')
                ->nullable()
                ->constrained('pickups')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Payout Relationship
            |--------------------------------------------------------------------------
            |
            | Optional: commission can be linked to the payout record.
            |
            */

            $table->foreignId('payout_id')
                ->nullable()
                ->constrained('payouts')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Institution / Client
            |--------------------------------------------------------------------------
            |
            | The institution that sold the e-waste.
            |
            */

            $table->foreignId('institution_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Commission Reference
            |--------------------------------------------------------------------------
            |
            | Example: COM-2026-000001
            |
            */

            $table->string('commission_reference')->unique();

            /*
            |--------------------------------------------------------------------------
            | Amount Calculation
            |--------------------------------------------------------------------------
            |
            | gross_amount        = full final price of the deal
            | commission_rate     = percentage taken by platform
            | commission_amount   = amount kept by platform
            | institution_amount  = amount remaining for institution
            |
            */

            $table->decimal('gross_amount', 12, 2)->default(0);

            $table->decimal('commission_rate', 5, 2)->default(0);
            // Example: 5.00 means 5%

            $table->decimal('commission_amount', 12, 2)->default(0);

            $table->decimal('institution_amount', 12, 2)->default(0);

            $table->string('currency')->default('RWF');

            /*
            |--------------------------------------------------------------------------
            | Commission Type
            |--------------------------------------------------------------------------
            |
            | percentage = commission based on %
            | fixed      = fixed amount commission
            |
            */

            $table->enum('commission_type', [
                'percentage',
                'fixed',
            ])->default('percentage');

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            |
            | pending   = commission calculated but not finalized
            | confirmed = commission confirmed after collection/payment
            | paid      = commission already settled
            | cancelled = cancelled transaction
            | reversed  = reversed due to refund/error
            |
            */

            $table->enum('status', [
                'pending',
                'confirmed',
                'paid',
                'cancelled',
                'reversed',
            ])->default('pending');

            /*
            |--------------------------------------------------------------------------
            | Confirmation Information
            |--------------------------------------------------------------------------
            */

            $table->foreignId('confirmed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('confirmed_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Notes
            |--------------------------------------------------------------------------
            */

            $table->text('description')->nullable();

            $table->text('admin_note')->nullable();

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
            $table->index('pickup_id');
            $table->index('payout_id');
            $table->index('institution_id');
            $table->index('commission_reference');
            $table->index('commission_type');
            $table->index('status');
            $table->index('confirmed_by');
            $table->index('confirmed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};