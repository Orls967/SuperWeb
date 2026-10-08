<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_common_audit_run_schedulers', function (Blueprint $table) {
            $table->id();
            $table->string('run_code')->unique();
            $table->string('domain_name');
            $table->string('upstream_domain_name')->nullable();
            $table->boolean('upstream_audit_passed')->default(true); // 389.2 & 389.5 Edge case
            $table->boolean('downstream_audit_permitted')->default(true);
            $table->string('exit_code')->default('0');
            $table->timestamps();
        });

        Schema::create('global_common_audit_evidence_packs', function (Blueprint $table) {
            $table->id();
            $table->string('pack_code')->unique();
            $table->string('evidence_checksum'); // 389.3 & 389.4
            $table->boolean('pii_redacted')->default(true); // 389.3 & 389.6 Risk
            $table->boolean('checksum_verified')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_common_audit_evidence_packs');
        Schema::dropIfExists('global_common_audit_run_schedulers');
    }
};
