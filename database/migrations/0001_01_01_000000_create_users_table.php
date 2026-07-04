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
        /*
        |--------------------------------------------------------------------------
        | Roles Table
        |--------------------------------------------------------------------------
        |
        | This table stores system roles.
        | For now, we will seed only Admin role.
        |
        */

        Schema::create('roles', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();

            $table->text('description')->nullable();

            // Store role permissions as JSON
            $table->json('permissions')->nullable();

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Users Table
        |--------------------------------------------------------------------------
        |
        | Users can be:
        | - admin
        | - institution
        | - enviroserve_staff
        |
        */

        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Basic login information
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // Role and status
            $table->string('role')->default('institution')->index();
            $table->string('status')->default('active')->index();

            // Contact information
            $table->string('phone')->nullable();
            $table->text('address')->nullable();

            // Institution / client information
            $table->string('institution_name')->nullable();
            $table->string('institution_type')->nullable();

            // Location information
            $table->string('district')->nullable();
            $table->string('sector')->nullable();
            $table->string('cell')->nullable();
            $table->string('village')->nullable();

            // Enviroserve staff information
            $table->string('staff_code')->nullable()->unique();
            $table->string('staff_position')->nullable();

            // Wallet / monetization
            $table->decimal('wallet_balance', 12, 2)->default(0);
            $table->unsignedInteger('points_balance')->default(0);

            $table->rememberToken();
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Password Reset Tokens Table
        |--------------------------------------------------------------------------
        */

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        /*
        |--------------------------------------------------------------------------
        | Sessions Table
        |--------------------------------------------------------------------------
        */

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();

            $table->foreignId('user_id')
                ->nullable()
                ->index()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
    }
};