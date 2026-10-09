<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_stress_domain_capacity_envelopes', function (Blueprint $table) {
            $table->id();
            $table->string('domain_name')->unique();
            $table->integer('max_supported_tps');
            $table->boolean('sustainable_24h_basis')->default(true); // 392.1 & 392.6 Risk
            $table->timestamps();
        });

        Schema::create('global_stress_admission_control_events', function (Blueprint $table) {
            $table->id();
            $table->string('request_code')->unique();
            $table->string('domain_name')->index();
            $table->integer('incoming_tps');
            $table->boolean('request_admitted')->default(true);
            $table->string('rejection_reason')->nullable(); // 392.3, 392.4, 392.5 Edge case
            $table->boolean('data_invariants_preserved')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_stress_admission_control_events');
        Schema::dropIfExists('global_stress_domain_capacity_envelopes');
    }
};
