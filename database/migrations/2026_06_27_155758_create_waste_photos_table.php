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
        Schema::create('waste_photos', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Waste Listing Relationship
            |--------------------------------------------------------------------------
            |
            | Each photo belongs to one waste listing uploaded by an institution.
            |
            */

            $table->foreignId('waste_listing_id')
                ->constrained('waste_listings')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Uploaded By
            |--------------------------------------------------------------------------
            |
            | Usually this will be the institution/client user who uploaded the waste.
            |
            */

            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Photo File Information
            |--------------------------------------------------------------------------
            */

            $table->string('photo_path');
            // Example: waste_photos/laptop-image.jpg

            $table->string('original_name')->nullable();
            // Example: IMG_20260627_123.jpg

            $table->string('mime_type')->nullable();
            // Example: image/jpeg, image/png

            $table->unsignedBigInteger('file_size')->nullable();
            // File size in bytes

            $table->string('storage_disk')->default('public');
            // Example: public, local, s3

            /*
            |--------------------------------------------------------------------------
            | Photo Type
            |--------------------------------------------------------------------------
            |
            | This helps us know what kind of photo was uploaded.
            |
            */

            $table->enum('photo_type', [
                'main',
                'front',
                'back',
                'side',
                'serial_number',
                'damage',
                'other',
            ])->default('main');

            $table->boolean('is_primary')->default(false);
            // Main photo shown first in app

            $table->unsignedInteger('sort_order')->default(0);

            /*
            |--------------------------------------------------------------------------
            | AI Processing Control
            |--------------------------------------------------------------------------
            |
            | This helps the system know whether this photo has already been analyzed
            | by AI or not.
            |
            */

            $table->boolean('is_ai_processed')->default(false);

            $table->timestamp('ai_processed_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $table->enum('status', [
                'active',
                'inactive',
                'deleted',
            ])->default('active');

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index('waste_listing_id');
            $table->index('uploaded_by');
            $table->index('photo_type');
            $table->index('is_primary');
            $table->index('is_ai_processed');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waste_photos');
    }
};