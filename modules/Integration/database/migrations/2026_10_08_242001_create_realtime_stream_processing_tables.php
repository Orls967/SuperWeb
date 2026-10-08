<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_stream_messages', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('partition_key')->index();
            $table->string('stream_topic')->index();
            $table->boolean('is_monetary')->default(false); // 242.7
            $table->bigInteger('offset_number')->index();
            $table->json('payload_json');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('data_stream_consumer_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->string('consumer_id')->unique();
            $table->bigInteger('last_processed_offset')->default(0);
            $table->integer('current_stream_lag')->default(0);
            $table->integer('sla_lag_threshold')->default(500);
            $table->boolean('lag_alert_triggered')->default(false);
            $table->boolean('resync_from_snapshot_required')->default(false); // 242.6
            $table->timestamps();
        });

        Schema::create('data_stream_backpressure_buffers', function (Blueprint $table) {
            $table->id();
            $table->string('buffer_code')->unique();
            $table->string('system_load_status'); // NORMAL, OVERLOADED, DEGRADED
            $table->integer('monetary_queue_dropped')->default(0); // MUST ALWAYS BE 0 (242.4, 242.7)
            $table->integer('analytics_queue_dropped')->default(0);
            $table->timestamps();
        });

        Schema::create('data_stream_replay_audits', function (Blueprint $table) {
            $table->id();
            $table->string('audit_code')->unique();
            $table->bigInteger('from_offset');
            $table->bigInteger('to_offset');
            $table->decimal('replay_aggregate_sum', 15, 2);
            $table->decimal('batch_reconciled_sum', 15, 2);
            $table->boolean('is_mismatch_detected')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_stream_replay_audits');
        Schema::dropIfExists('data_stream_backpressure_buffers');
        Schema::dropIfExists('data_stream_consumer_checkpoints');
        Schema::dropIfExists('data_stream_messages');
    }
};
