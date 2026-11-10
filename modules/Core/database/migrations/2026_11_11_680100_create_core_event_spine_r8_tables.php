<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('core_consumer_inbox')) {
            Schema::create('core_consumer_inbox', function (Blueprint $table) {
                $table->id();
                $table->string('consumer_group', 100)->index();
                $table->string('message_id', 64)->index();
                $table->string('handler_class', 255)->nullable();
                $table->string('response_hash', 64)->nullable();
                $table->unsignedInteger('execution_time_ms')->default(0);
                $table->timestamp('processed_at')->useCurrent();
                $table->timestamps();

                $table->unique(['consumer_group', 'message_id'], 'inbox_group_msg_unique');
            });
        }

        if (! Schema::hasTable('core_spine_gaps')) {
            Schema::create('core_spine_gaps', function (Blueprint $table) {
                $table->id();
                $table->string('aggregate_type', 100)->index();
                $table->string('aggregate_id', 64)->index();
                $table->unsignedBigInteger('gap_start');
                $table->unsignedBigInteger('gap_end');
                $table->string('status', 20)->default('open')->index(); // open, resolved, ignored
                $table->timestamp('detected_at')->useCurrent();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['aggregate_type', 'aggregate_id', 'status'], 'spine_gaps_agg_status_idx');
            });
        }

        if (! Schema::hasTable('core_poison_messages')) {
            Schema::create('core_poison_messages', function (Blueprint $table) {
                $table->id();
                $table->string('message_key', 64)->index();
                $table->string('consumer_group', 100)->index();
                $table->string('topic', 100);
                $table->string('event_name', 100)->nullable();
                $table->json('payload');
                $table->text('failure_reason');
                $table->string('exception_class', 255)->nullable();
                $table->text('stack_trace')->nullable();
                $table->unsignedTinyInteger('attempts_count')->default(3);
                $table->timestamp('quarantined_at')->useCurrent();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('core_event_deliveries')) {
            Schema::create('core_event_deliveries', function (Blueprint $table) {
                $table->id();
                $table->string('message_id', 64)->index();
                $table->string('consumer_group', 100)->index();
                $table->string('topic', 100);
                $table->string('status', 20)->default('pending')->index(); // pending, dispatched, delivered, retrying, dead_lettered, replayed, discarded
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->timestamp('next_retry_at')->nullable()->index();
                $table->timestamp('dispatched_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();

                $table->unique(['message_id', 'consumer_group'], 'deliveries_msg_group_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('core_event_deliveries');
        Schema::dropIfExists('core_poison_messages');
        Schema::dropIfExists('core_spine_gaps');
        Schema::dropIfExists('core_consumer_inbox');
    }
};
