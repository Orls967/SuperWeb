<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plt_software_supply_chain_artifacts', function (Blueprint $table) {
            $table->id();
            $table->string('artifact_name');
            $table->string('version');
            $table->string('expected_checksum_sha256');
            $table->string('computed_checksum_sha256');
            $table->boolean('checksum_verified')->default(false); // 430.1, 430.4
            $table->boolean('vulnerability_scan_passed')->default(false);
            $table->boolean('license_compliant')->default(true);
            $table->timestamps();
        });

        Schema::create('plt_security_threat_models', function (Blueprint $table) {
            $table->id();
            $table->string('model_code')->unique();
            $table->string('domain_name'); // health, finance, energy, venue (430.3)
            $table->boolean('is_high_impact_domain')->default(true);
            $table->boolean('security_sign_off_completed')->default(false); // 430.3, 430.4, 430.5
            $table->string('lead_security_architect')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plt_security_threat_models');
        Schema::dropIfExists('plt_software_supply_chain_artifacts');
    }
};
