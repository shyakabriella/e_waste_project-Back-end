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
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Payout Receiver
            |--------------------------------------------------------------------------
            |
            | This is the institution/client who receives money or points.
            |
            */

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Waste Listing Relationship
            |--------------------------------------------------------------------------
            |
            | The waste listing that generated this payout.
            |
            */

            $table->foreignId('waste_listing_id')
                ->nullable()
                ->constrained('waste_listings')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Pickup Relationship
            |--------------------------------------------------------------------------
            |
            | Optional: payout can be linked to the pickup after collection.
            |
            */

            $table->foreignId('pickup_id')
                ->nullable()
                ->constrained('pickups')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Wallet Transaction Relationship
            |--------------------------------------------------------------------------
            |
            | Optional: connects payout to wallet transaction record.
            |
            */

            $table->foreignId('wallet_transaction_id')
                ->nullable()
                ->constrained('wallet_transactions')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Payout Reference
            |--------------------------------------------------------------------------
            |
            | Example: PAY-2026-000001
            |
            */

            $table->string('payout_reference')->unique();

            /*
            |--------------------------------------------------------------------------
            | Payment Amount
            |--------------------------------------------------------------------------
            */

            $table->decimal('gross_amount', 12, 2)->default(0);
            // Full amount before commission/deductions

            $table->decimal('commission_amount', 12, 2)->default(0);
            // Platform commission

            $table->decimal('net_amount', 12, 2)->default(0);
            // Amount paid to institution/client

            $table->string('currency')->default('RWF');

            /*
            |--------------------------------------------------------------------------
            | Points Payout
            |--------------------------------------------------------------------------
            |
            | If the system uses points instead of money.
            |
            */

            $table->integer('points')->default(0);

            /*
            |--------------------------------------------------------------------------
            | Payment Method
            |--------------------------------------------------------------------------
            */

            $table->enum('payment_method', [
                'cash',
                'mobile_money',
                'bank_transfer',
                'wallet',
                'points',
            ])->default('wallet');

            $table->string('payment_reference')->nullable();
            // Example: MTN MoMo transaction id, bank reference, receipt number

            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('mobile_money_phone')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Payout Status
            |--------------------------------------------------------------------------
            |
            | pending    = waiting for approval/payment
            | approved   = approved by admin/finance
            | paid       = payment completed
            | failed     = payment failed
            | cancelled  = payout cancelled
            | reversed   = payout reversed
            |
            */

            $table->enum('status', [
                'pending',
                'approved',
                'paid',
                'failed',
                'cancelled',
                'reversed',
            ])->default('pending');

            /*
            |--------------------------------------------------------------------------
            | Approval and Payment Information
            |--------------------------------------------------------------------------
            */

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();

            $table->foreignId('paid_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('paid_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Failure / Cancellation Information
            |--------------------------------------------------------------------------
            */

            $table->text('failure_reason')->nullable();
            $table->text('cancellation_reason')->nullable();

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

            $table->index('user_id');
            $table->index('waste_listing_id');
            $table->index('pickup_id');
            $table->index('wallet_transaction_id');
            $table->index('payout_reference');
            $table->index('payment_method');
            $table->index('status');
            $table->index('approved_by');
            $table->index('paid_by');
            $table->index('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};