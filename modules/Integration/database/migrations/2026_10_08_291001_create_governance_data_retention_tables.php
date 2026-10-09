<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_legal_holds', function (Blueprint $table) {
            $table->id();
            $table->string('hold_matter_code')->unique();
            $table->string('legal_matter_title');
            $table->string('target_entity_or_person');
            $table->boolean('is_active')->default(true); // 291.2 & 291.5
            $table->string('legal_approver_id')->nullable();
            $table->timestamps();
        });

        Schema::create('gov_retention_records', function (Blueprint $table) {
            $table->id();
            $table->string('record_code')->unique();
            $table->string('record_class'); // FINANCIAL_LEDGER, CUSTOMER_PII, AUDIT_LOG
            $table->string('associated_hold_matter_code')->nullable()->index();
            $table->boolean('is_financial_ledger_immutable')->default(false); // 291.3 & 291.5 Ledger never deleted
            $table->date('retention_expiry_date');
            $table->string('disposition_status')->default('ACTIVE'); // ACTIVE, ARCHIVED, ANONYMIZED, SKIPPED_LEGAL_HOLD
            $table->timestamps();
        });

        Schema::create('gov_ediscovery_exports', function (Blueprint $table) {
            $table->id();
            $table->string('export_code')->unique();
            $table->string('matter_scope');
            $table->string('authorized_recipient_id'); // 291.7 Recipient recorded
            $table->string('hash_verification_sha256'); // 291.4
            $table->boolean('attorney_client_privilege_filtered')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_ediscovery_exports');
        Schema::dropIfExists('gov_retention_records');
        Schema::dropIfExists('gov_legal_holds');
    }
};
