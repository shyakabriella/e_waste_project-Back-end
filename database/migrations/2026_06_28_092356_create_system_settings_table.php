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
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Setting Key
            |--------------------------------------------------------------------------
            |
            | Example:
            | default_currency
            | platform_commission_rate
            | ai_analysis_enabled
            | gps_tracking_enabled
            |
            */

            $table->string('key')->unique();

            /*
            |--------------------------------------------------------------------------
            | Setting Value
            |--------------------------------------------------------------------------
            |
            | The value can be string, number, boolean, JSON, etc.
            |
            */

            $table->longText('value')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Value Type
            |--------------------------------------------------------------------------
            |
            | string  = normal text
            | integer = whole number
            | decimal = money/rate value
            | boolean = true/false
            | json    = structured data
            |
            */

            $table->enum('type', [
                'string',
                'integer',
                'decimal',
                'boolean',
                'json',
            ])->default('string');

            /*
            |--------------------------------------------------------------------------
            | Group
            |--------------------------------------------------------------------------
            |
            | Helps organize settings in Admin dashboard.
            |
            */

            $table->string('group')->default('general');
            // Example: general, ai, gps, wallet, payment, security

            /*
            |--------------------------------------------------------------------------
            | Description
            |--------------------------------------------------------------------------
            */

            $table->text('description')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Control
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_public')->default(false);
            // true = mobile app can read this setting

            $table->boolean('is_editable')->default(true);
            // false = system setting cannot be edited from dashboard

            /*
            |--------------------------------------------------------------------------
            | Updated By
            |--------------------------------------------------------------------------
            */

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $table->enum('status', [
                'active',
                'inactive',
            ])->default('active');

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index('key');
            $table->index('group');
            $table->index('type');
            $table->index('is_public');
            $table->index('is_editable');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};