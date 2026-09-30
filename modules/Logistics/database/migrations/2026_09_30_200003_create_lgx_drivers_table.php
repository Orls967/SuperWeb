<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgx_drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('driver_number')->unique(); // DRV-BDJ-001
            $table->string('license_class'); // SIM B1 Umum, SIM B2 Umum, SIM B1, SIM B2, SIM A
            $table->date('license_expiry');
            $table->foreignId('home_hub_id')->constrained('lgx_locations')->cascadeOnDelete();
            $table->string('status')->default('available'); // available, on_duty, resting, suspended
            $table->unsignedInteger('daily_driving_minutes')->default(0); // UU 22/2009 max 480 min (8h)
            $table->unsignedInteger('continuous_driving_minutes')->default(0); // UU 22/2009 max 240 min (4h) before rest
            $table->timestamp('last_rest_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'license_class']);
            $table->index('home_hub_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_drivers');
    }
};
