<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'vehicle_plate')) {
                $table->string('vehicle_plate')->nullable()->after('staff_position');
            }

            if (!Schema::hasColumn('users', 'vehicle_number')) {
                $table->string('vehicle_number')->nullable()->after('vehicle_plate');
            }

            if (!Schema::hasColumn('users', 'vehicle_type')) {
                $table->string('vehicle_type')->nullable()->after('vehicle_number');
            }

            if (!Schema::hasColumn('users', 'license_number')) {
                $table->string('license_number')->nullable()->after('vehicle_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['license_number', 'vehicle_type', 'vehicle_number', 'vehicle_plate'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
