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
        Schema::create('pickups', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Waste Listing
            |--------------------------------------------------------------------------
            |
            | The waste item/listing that will be picked up.
            |
            */

            $table->foreignId('waste_listing_id')
                ->constrained('waste_listings')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Institution / Client
            |--------------------------------------------------------------------------
            |
            | The institution that uploaded the waste.
            |
            */

            $table->foreignId('institution_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Assigned Enviroserve Staff / Driver
            |--------------------------------------------------------------------------
            |
            | assigned_staff_id = staff who manages/assigns pickup
            | driver_id         = person going to collect waste
            |
            */

            $table->foreignId('assigned_staff_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('driver_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Pickup Code
            |--------------------------------------------------------------------------
            |
            | Useful for tracking pickup reference.
            | Example: PCK-2026-00001
            |
            */

            $table->string('pickup_code')->unique();

            /*
            |--------------------------------------------------------------------------
            | Pickup Schedule
            |--------------------------------------------------------------------------
            */

            $table->date('pickup_date')->nullable();

            $table->time('pickup_time')->nullable();

            $table->dateTime('scheduled_at')->nullable();

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
            | Pickup Status
            |--------------------------------------------------------------------------
            |
            | pending    = pickup not yet scheduled
            | scheduled  = pickup date/time assigned
            | on_the_way = driver/staff is moving to institution
            | arrived    = driver/staff arrived at pickup location
            | collected  = waste collected
            | completed  = pickup and payment completed
            | cancelled  = pickup cancelled
            | failed     = pickup failed
            |
            */

            $table->enum('status', [
                'pending',
                'scheduled',
                'on_the_way',
                'arrived',
                'collected',
                'completed',
                'cancelled',
                'failed',
            ])->default('pending');

            /*
            |--------------------------------------------------------------------------
            | Pickup Progress Time
            |--------------------------------------------------------------------------
            */

            $table->timestamp('started_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Confirmation
            |--------------------------------------------------------------------------
            |
            | Both sides can confirm collection.
            |
            */

            $table->boolean('institution_confirmed')->default(false);

            $table->foreignId('institution_confirmed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('institution_confirmed_at')->nullable();

            $table->boolean('staff_confirmed')->default(false);

            $table->foreignId('staff_confirmed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('staff_confirmed_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Collection Details
            |--------------------------------------------------------------------------
            */

            $table->decimal('collected_weight_kg', 10, 2)->nullable();

            $table->unsignedInteger('collected_quantity')->nullable();

            $table->text('collection_notes')->nullable();

            $table->text('cancellation_reason')->nullable();

            /*
            |--------------------------------------------------------------------------
            | GPS Tracking Control
            |--------------------------------------------------------------------------
            */

            $table->boolean('gps_tracking_enabled')->default(true);

            $table->timestamp('gps_tracking_started_at')->nullable();

            $table->timestamp('gps_tracking_stopped_at')->nullable();

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
            $table->index('institution_id');
            $table->index('assigned_staff_id');
            $table->index('driver_id');
            $table->index('pickup_code');
            $table->index('pickup_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pickups');
    }
};