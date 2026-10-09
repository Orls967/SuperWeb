<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_process_mesh_idempotent_commands', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key')->unique(); // 387.2, 387.4, 387.5 Edge case
            $table->string('command_payload');
            $table->integer('execution_count')->default(1);
            $table->timestamps();
        });

        Schema::create('global_process_mesh_event_replays', function (Blueprint $table) {
            $table->id();
            $table->string('replay_batch_code')->unique();
            $table->boolean('is_read_model_handler')->default(true); // 387.3 & 387.6 Risk
            $table->boolean('external_side_effect_replayed')->default(false); // 387.3 & 387.4
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_process_mesh_event_replays');
        Schema::dropIfExists('global_process_mesh_idempotent_commands');
    }
};
