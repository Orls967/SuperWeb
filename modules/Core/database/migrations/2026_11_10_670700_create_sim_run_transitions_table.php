<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sim_run_transitions')) {
            Schema::create('sim_run_transitions', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('run_id')->constrained('sim_runs')->cascadeOnDelete();
                $table->string('from_status', 32);
                $table->string('to_status', 32);
                $table->string('reason', 255);
                $table->string('actor_identifier', 64)->nullable();
                $table->string('actor_role', 32)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sim_run_transitions');
    }
};
