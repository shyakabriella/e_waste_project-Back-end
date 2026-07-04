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
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | User / Wallet Owner
            |--------------------------------------------------------------------------
            |
            | This is the institution/client who owns the wallet.
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
            | Optional because some wallet transactions may not come directly
            | from one waste listing.
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
            | Optional: links the transaction to the pickup if payment is made
            | after collection.
            |
            */

            $table->foreignId('pickup_id')
                ->nullable()
                ->constrained('pickups')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Transaction Reference
            |--------------------------------------------------------------------------
            |
            | Example: WTX-2026-000001
            |
            */

            $table->string('transaction_reference')->unique();

            /*
            |--------------------------------------------------------------------------
            | Transaction Type
            |--------------------------------------------------------------------------
            |
            | credit        = money added to wallet
            | debit         = money removed from wallet
            | points_credit = points added
            | points_debit  = points removed
            | payout        = money paid out to institution
            | commission    = platform commission transaction
            | adjustment    = manual correction by admin
            |
            */

            $table->enum('type', [
                'credit',
                'debit',
                'points_credit',
                'points_debit',
                'payout',
                'commission',
                'adjustment',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Money Information
            |--------------------------------------------------------------------------
            */

            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency')->default('RWF');

            /*
            |--------------------------------------------------------------------------
            | Points Information
            |--------------------------------------------------------------------------
            */

            $table->integer('points')->default(0);

            /*
            |--------------------------------------------------------------------------
            | Balance After Transaction
            |--------------------------------------------------------------------------
            |
            | This helps us know the wallet balance after every transaction.
            |
            */

            $table->decimal('balance_before', 12, 2)->default(0);
            $table->decimal('balance_after', 12, 2)->default(0);

            $table->integer('points_before')->default(0);
            $table->integer('points_after')->default(0);

            /*
            |--------------------------------------------------------------------------
            | Payment Method
            |--------------------------------------------------------------------------
            |
            | This is optional because not all wallet transactions are payments.
            |
            */

            $table->enum('payment_method', [
                'wallet',
                'cash',
                'mobile_money',
                'bank_transfer',
                'points',
                'system',
            ])->nullable();

            $table->string('payment_reference')->nullable();
            // Example: MOMO transaction id, bank reference, or manual receipt number

            /*
            |--------------------------------------------------------------------------
            | Transaction Status
            |--------------------------------------------------------------------------
            */

            $table->enum('status', [
                'pending',
                'completed',
                'failed',
                'cancelled',
                'reversed',
            ])->default('pending');

            /*
            |--------------------------------------------------------------------------
            | Description and Notes
            |--------------------------------------------------------------------------
            */

            $table->string('title')->nullable();
            // Example: Payment for collected old laptops

            $table->text('description')->nullable();

            $table->text('admin_note')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Created / Approved By
            |--------------------------------------------------------------------------
            */

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();

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
            $table->index('transaction_reference');
            $table->index('type');
            $table->index('status');
            $table->index('payment_method');
            $table->index('created_by');
            $table->index('approved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};