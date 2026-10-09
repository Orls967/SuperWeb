<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 204.2: Vulnerability management findings and SLA tracking
        Schema::create('erm_cyber_vulnerabilities', function (Blueprint $table) {
            $table->id();
            $table->string('vuln_code')->unique();
            $table->string('asset_service_name');
            $table->string('severity'); // CRITICAL, HIGH, MEDIUM, LOW
            $table->integer('sla_hours_remediation');
            $table->string('remediation_status')->default('OPEN'); // OPEN, CLOSED, BREACHED
            $table->timestamp('discovered_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        // 204.3 & 204.4: Cyber incident containment and ransomware ledger reconstruction drill
        Schema::create('erm_incident_containments', function (Blueprint $table) {
            $table->id();
            $table->string('incident_code')->unique();
            $table->string('affected_module');
            $table->boolean('access_tokens_revoked')->default(true);
            $table->boolean('module_isolated')->default(true);
            $table->decimal('reconstructed_ledger_discrepancy', 18, 2)->default(0.00); // Drill ledger integrity Σ=0
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erm_incident_containments');
        Schema::dropIfExists('erm_cyber_vulnerabilities');
    }
};
