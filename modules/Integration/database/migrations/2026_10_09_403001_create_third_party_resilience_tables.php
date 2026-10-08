<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_vendor_concentrations', function (Blueprint $table) {
            $table->id();
            $table->string('vendor_code')->unique();
            $table->string('vendor_name');
            $table->string('category'); // cloud, logistics, payment, insurance, etc.
            $table->decimal('spend_percentage', 5, 2); // % of category spend
            $table->decimal('concentration_limit', 5, 2)->default(40.00); // 403.4
            $table->string('alternate_vendor_code')->nullable();
            $table->boolean('alternate_qualified')->default(false); // 403.6
            $table->boolean('has_emergency_playbook')->default(false); // 403.5
            $table->timestamps();
        });

        Schema::create('gov_vendor_continuity_rehearsals', function (Blueprint $table) {
            $table->id();
            $table->string('rehearsal_code')->unique();
            $table->string('vendor_code');
            $table->string('drill_type'); // substitution, exit_strategy, sudden_failure
            $table->integer('recovery_time_minutes');
            $table->boolean('data_exported')->default(true);
            $table->boolean('credentials_rotated')->default(true);
            $table->boolean('passed')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_vendor_continuity_rehearsals');
        Schema::dropIfExists('gov_vendor_concentrations');
    }
};
