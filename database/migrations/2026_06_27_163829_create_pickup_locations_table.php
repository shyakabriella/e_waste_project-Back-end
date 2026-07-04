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
        Schema::create('pickup_locations', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Pickup Relationship
            |--------------------------------------------------------------------------
            |
            | This location belongs to one pickup.
            |
            */

            $table->foreignId('pickup_id')
                ->constrained('pickups')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Driver / Staff Location Owner
            |--------------------------------------------------------------------------
            |
            | Usually this is the driver or Enviroserve staff whose location is tracked.
            |
            */

            $table->foreignId('driver_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | GPS Coordinates
            |--------------------------------------------------------------------------
            */

            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            /*
            |--------------------------------------------------------------------------
            | Extra GPS Information
            |--------------------------------------------------------------------------
            */

            $table->decimal('accuracy', 8, 2)->nullable();
            // GPS accuracy in meters

            $table->decimal('speed', 8, 2)->nullable();
            // Speed in km/h or m/s depending on mobile app logic

            $table->decimal('heading', 5, 2)->nullable();
            // Direction angle from 0 to 360 degrees

            $table->decimal('altitude', 10, 2)->nullable();
            // Height above sea level, optional

            /*
            |--------------------------------------------------------------------------
            | Tracking Status
            |--------------------------------------------------------------------------
            |
            | active     = valid tracking point
            | inaccurate = GPS accuracy is weak
            | ignored    = system ignored this point
            |
            */

            $table->enum('status', [
                'active',
                'inaccurate',
                'ignored',
            ])->default('active');

            /*
            |--------------------------------------------------------------------------
            | Current Location Control
            |--------------------------------------------------------------------------
            |
            | If true, this is the latest known location for this pickup.
            |
            */

            $table->boolean('is_current')->default(false);

            /*
            |--------------------------------------------------------------------------
            | Device / Source Information
            |--------------------------------------------------------------------------
            */

            $table->string('source')->nullable();
            // Example: android_app, web_dashboard

            $table->string('device_id')->nullable();

            $table->integer('battery_level')->nullable();
            // Example: 85 means 85%

            /*
            |--------------------------------------------------------------------------
            | Recorded Time
            |--------------------------------------------------------------------------
            |
            | recorded_at is the real time the mobile device captured the GPS point.
            |
            */

            $table->timestamp('recorded_at')->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index('pickup_id');
            $table->index('driver_id');
            $table->index('is_current');
            $table->index('status');
            $table->index('recorded_at');
            $table->index(['latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pickup_locations');
    }
};