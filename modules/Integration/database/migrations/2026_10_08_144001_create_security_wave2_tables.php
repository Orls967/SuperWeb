<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Zero Trust: Service Identity Registry ──────────────────────────────
        Schema::create('sec_service_identities', function (Blueprint $table) {
            $table->id();
            $table->string('service_name')->unique();          // e.g. "modules.egy", "modules.ret"
            $table->string('line_code', 10);                  // EGY, TLX, MED, EDU, RET, etc.
            $table->string('sanctum_token_hash', 128)->nullable();
            $table->json('allowed_abilities');                 // least-privilege abilities
            $table->string('device_trust_level')->default('STANDARD'); // STANDARD|HIGH|CRITICAL
            $table->string('mtls_cert_fingerprint', 128)->nullable();  // mTLS sim
            $table->timestamp('cert_issued_at')->nullable();
            $table->timestamp('cert_expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['line_code', 'is_active']);
        });

        // ─── Zero Trust: Daily Access Audit Log ────────────────────────────────
        Schema::create('sec_access_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('caller_service');
            $table->string('callee_service');
            $table->string('action');
            $table->string('status', 20); // ALLOWED|DENIED|ESCALATED
            $table->string('ability_used')->nullable();
            $table->ipAddress('source_ip')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('accessed_at');
            $table->timestamps();
            $table->index(['caller_service', 'status']);
            $table->index(['accessed_at']);
        });

        // ─── Privacy Vault: PII Records ─────────────────────────────────────────
        Schema::create('sec_pii_vault_records', function (Blueprint $table) {
            $table->id();
            $table->string('subject_id', 100);                // Party/User ID
            $table->string('pii_category', 30);               // MEDICAL|BIOMETRIC|FINANCIAL|LOCATION
            $table->string('line_code', 10);                  // Which lini owns this PII
            $table->text('encrypted_value');                   // AES-256 encrypted
            $table->string('vault_token', 128)->unique();      // Tokenized reference for analytics
            $table->string('encryption_key_version', 20)->default('v1');
            $table->boolean('is_active')->default(true);       // false = erased/anonymized
            $table->timestamp('anonymized_at')->nullable();
            $table->timestamps();
            $table->index(['subject_id', 'pii_category']);
            $table->index(['vault_token']);
        });

        // ─── Privacy Vault: Consent Ledger ─────────────────────────────────────
        Schema::create('sec_consent_ledger', function (Blueprint $table) {
            $table->id();
            $table->string('subject_id', 100);
            $table->string('line_code', 10);
            $table->string('purpose_code', 50);               // ANALYTICS|MARKETING|OPERATIONAL|RESEARCH
            $table->string('status', 20);                     // OPTED_IN|OPTED_OUT|WITHDRAWN
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->string('legal_basis', 50)->default('LEGITIMATE_INTEREST'); // CONSENT|CONTRACT|LEGAL_OBLIGATION
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['subject_id', 'line_code', 'purpose_code']);
            $table->index(['subject_id', 'status']);
        });

        // ─── Privacy Vault: Erasure Requests ───────────────────────────────────
        Schema::create('sec_erasure_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_code', 50)->unique();
            $table->string('subject_id', 100);
            $table->string('status', 20)->default('PENDING'); // PENDING|IN_PROGRESS|COMPLETED|PARTIAL
            $table->json('lines_to_erase');
            $table->json('erasure_report')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['subject_id', 'status']);
        });

        // ─── Regulatory Compliance: Obligation Calendar ─────────────────────────
        Schema::create('sec_regulatory_obligations', function (Blueprint $table) {
            $table->id();
            $table->string('obligation_code', 80)->unique();
            $table->string('line_code', 10);
            $table->string('regulation_name', 200);            // e.g. "UU No. 29/2004 (Kedokteran)"
            $table->string('category', 30);                    // LICENSE|REPORTING|CERTIFICATION|AUDIT
            $table->date('due_date');
            $table->string('status', 30)->default('PENDING'); // PENDING|COMPLIANT|OVERDUE|BLOCKED
            $table->string('escalation_level', 20)->default('LOW'); // LOW|MEDIUM|HIGH|CRITICAL
            $table->string('owner_email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            $table->index(['line_code', 'status']);
            $table->index(['due_date', 'status']);
        });

        // ─── Threat Detection: Incident Records ────────────────────────────────
        Schema::create('sec_security_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_code', 80)->unique();
            $table->string('incident_class', 30);              // RANSOMWARE|DATA_LEAK|PAYMENT_FRAUD|UNAUTHORIZED_ACCESS
            $table->string('severity', 20);                    // P1|P2|P3|P4
            $table->string('status', 30)->default('DETECTED'); // DETECTED|INVESTIGATING|CONTAINED|RESOLVED|CLOSED
            $table->json('affected_lines');
            $table->text('description');
            $table->json('playbook_steps')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('contained_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('postmortem')->nullable();
            $table->text('capa')->nullable();                  // Corrective and Preventive Actions
            $table->text('regulator_report')->nullable();
            $table->timestamps();
            $table->index(['incident_class', 'severity']);
            $table->index(['status', 'detected_at']);
        });

        // ─── Security: Penetration Test Results ────────────────────────────────
        Schema::create('sec_pentest_results', function (Blueprint $table) {
            $table->id();
            $table->string('test_run_code', 80)->unique();
            $table->string('test_type', 30);                   // IDOR|PRIVILEGE_ESCALATION|FUZZING|SQLI|XSS
            $table->string('target_route')->nullable();
            $table->string('target_role')->nullable();
            $table->string('line_code', 10)->nullable();
            $table->boolean('vulnerability_found')->default(false);
            $table->text('finding_detail')->nullable();
            $table->string('severity', 20)->nullable();
            $table->timestamp('tested_at');
            $table->timestamps();
            $table->index(['test_type', 'vulnerability_found']);
            $table->index(['line_code', 'vulnerability_found']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sec_pentest_results');
        Schema::dropIfExists('sec_security_incidents');
        Schema::dropIfExists('sec_regulatory_obligations');
        Schema::dropIfExists('sec_erasure_requests');
        Schema::dropIfExists('sec_consent_ledger');
        Schema::dropIfExists('sec_pii_vault_records');
        Schema::dropIfExists('sec_access_audit_logs');
        Schema::dropIfExists('sec_service_identities');
    }
};
