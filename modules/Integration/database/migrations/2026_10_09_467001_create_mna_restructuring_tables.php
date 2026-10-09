<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sim_mna_restructuring_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_code')->unique();
            $table->string('target_entity_code');
            $table->integer('migrated_records_count')->default(0);
            $table->boolean('is_idempotent')->default(true); // 467.2, 467.4
            $table->boolean('audit_trail_preserved')->default(true); // 467.3, 467.6
            $table->boolean('duplicate_master_data_detected')->default(false);
            $table->string('status')->default('initiated'); // initiated, completed, rolled_back_clean
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sim_mna_restructuring_events');
    }
};
