<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_event_spine_replay_executions', function (Blueprint $table) {
            $table->id();
            $table->string('replay_code')->unique();
            $table->string('event_topic');
            $table->boolean('approval_granted')->default(false); // 369.2 & 369.4
            $table->boolean('external_side_effects_disabled')->default(true); // 369.5 Edge case
            $table->boolean('ledger_double_post_blocked')->default(true); // 369.4
            $table->boolean('replay_succeeded')->default(false);
            $table->timestamps();
        });

        Schema::create('platform_event_spine_dlq_triages', function (Blueprint $table) {
            $table->id();
            $table->string('dlq_code')->unique();
            $table->string('event_topic')->index();
            $table->string('assigned_owner')->nullable(); // 369.3 & 369.6 Risk
            $table->integer('age_hours')->default(0);
            $table->boolean('aging_alert_escalated')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_event_spine_dlq_triages');
        Schema::dropIfExists('platform_event_spine_replay_executions');
    }
};
