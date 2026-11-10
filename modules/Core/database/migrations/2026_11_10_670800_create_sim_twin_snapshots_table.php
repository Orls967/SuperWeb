<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sim_twin_snapshots')) {
            Schema::create('sim_twin_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('run_id')->constrained('sim_runs')->cascadeOnDelete();
                $table->integer('day_number');
                $table->string('entity_type', 64);
                $table->string('entity_id', 64);
                $table->string('state_hash', 64);
                $table->json('state_payload');
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['run_id', 'day_number', 'entity_type', 'entity_id'], 'sim_twin_snap_uniq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sim_twin_snapshots');
    }
};
