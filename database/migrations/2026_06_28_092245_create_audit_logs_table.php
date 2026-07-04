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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | User Who Performed Action
            |--------------------------------------------------------------------------
            |
            | The user who made the action.
            | Example: admin created user, staff verified waste, client uploaded waste.
            |
            */

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Action Information
            |--------------------------------------------------------------------------
            */

            $table->string('action');
            // Example: created, updated, deleted, login, logout, approved, rejected, paid

            $table->string('module');
            // Example: users, roles, waste_listings, pickups, payouts, wallet

            $table->string('event')->nullable();
            // Example: user_created, waste_verified, pickup_scheduled, payout_paid

            $table->text('description')->nullable();
            // Human-readable explanation of what happened

            /*
            |--------------------------------------------------------------------------
            | Record Information
            |--------------------------------------------------------------------------
            |
            | This helps us know which record was affected.
            | Example:
            | auditable_type = App\Models\WasteListing
            | auditable_id = 5
            |
            */

            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Old and New Values
            |--------------------------------------------------------------------------
            |
            | Used when something is updated.
            | Example:
            | old_values: {"status": "pending"}
            | new_values: {"status": "verified"}
            |
            */

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Request Information
            |--------------------------------------------------------------------------
            */

            $table->string('ip_address', 45)->nullable();

            $table->text('user_agent')->nullable();

            $table->string('request_method')->nullable();
            // Example: GET, POST, PUT, DELETE

            $table->string('request_url')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            |
            | success = action completed
            | failed  = action failed
            |
            */

            $table->enum('status', [
                'success',
                'failed',
            ])->default('success');

            $table->text('error_message')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Extra Data
            |--------------------------------------------------------------------------
            |
            | Any extra data we may need later.
            |
            */

            $table->json('metadata')->nullable();

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
            $table->index('action');
            $table->index('module');
            $table->index('event');
            $table->index('auditable_type');
            $table->index('auditable_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};