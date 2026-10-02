<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgx_dispatch_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('lgx_schedules')->cascadeOnDelete();
            $table->foreignId('truck_id')->constrained('lgx_trucks')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('lgx_drivers')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('trip_minutes');
            $table->string('status', 16)->default('active'); // active, released
            $table->string('release_reason', 255)->nullable();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['schedule_id', 'status']);
            $table->index(['driver_id', 'status']);
            $table->index(['truck_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_dispatch_assignments');
    }
};
