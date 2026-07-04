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
        Schema::create('qr_tags', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Waste Listing Relationship
            |--------------------------------------------------------------------------
            |
            | QR tag belongs to one uploaded waste listing.
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
            | Optional because QR can be created before pickup is scheduled.
            |
            */

            $table->foreignId('pickup_id')
                ->nullable()
                ->constrained('pickups')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | QR Code Information
            |--------------------------------------------------------------------------
            */

            $table->string('qr_code')->unique();
            // Example: QR-WASTE-2026-000001

            $table->string('qr_image_path')->nullable();
            // Example: qr_tags/QR-WASTE-2026-000001.png

            $table->string('qr_type')->default('waste_tracking');
            // Example: waste_tracking, pickup_confirmation

            /*
            |--------------------------------------------------------------------------
            | Tag Status
            |--------------------------------------------------------------------------
            |
            | generated = QR created but not scanned yet
            | printed   = QR tag printed
            | attached  = QR attached to waste item
            | scanned   = QR scanned by staff
            | used      = QR used for final confirmation
            | cancelled = QR cancelled
            |
            */

            $table->enum('status', [
                'generated',
                'printed',
                'attached',
                'scanned',
                'used',
                'cancelled',
            ])->default('generated');

            /*
            |--------------------------------------------------------------------------
            | Created / Printed Information
            |--------------------------------------------------------------------------
            */

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('printed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('printed_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Scanning Information
            |--------------------------------------------------------------------------
            |
            | Enviroserve staff scans QR tag during verification or pickup.
            |
            */

            $table->foreignId('scanned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('scanned_at')->nullable();

            $table->enum('scan_purpose', [
                'verification',
                'pickup',
                'collection_confirmation',
                'other',
            ])->nullable();

            /*
            |--------------------------------------------------------------------------
            | Scan Location
            |--------------------------------------------------------------------------
            */

            $table->decimal('scan_latitude', 10, 7)->nullable();
            $table->decimal('scan_longitude', 10, 7)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Notes
            |--------------------------------------------------------------------------
            */

            $table->text('notes')->nullable();

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
            $table->index('qr_code');
            $table->index('status');
            $table->index('created_by');
            $table->index('scanned_by');
            $table->index('scanned_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qr_tags');
    }
};