<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sim_checkpoints')) {
            Schema::create('sim_checkpoints', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('run_id')->constrained('sim_runs')->cascadeOnDelete();
                $table->integer('day_number');
                $table->string('chain_hash', 64);
                $table->json('handler_hashes');
                $table->json('state_summary');
                $table->timestamps();

                $table->unique(['run_id', 'day_number']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sim_checkpoints');
    }
};
