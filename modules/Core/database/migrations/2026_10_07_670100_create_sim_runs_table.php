<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sim_runs')) {
            Schema::create('sim_runs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name')->unique();
                $table->timestamp('virtual_now');
                $table->timestamp('sim_start_at');
                $table->timestamp('sim_end_at')->nullable();
                $table->integer('speed_multiplier')->default(1);
                $table->string('status')->default('idle'); // idle, running, paused, completed
                $table->unsignedBigInteger('seed')->default(42);
                $table->json('metrics')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sim_runs');
    }
};
