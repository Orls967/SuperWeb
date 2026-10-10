<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Klausul Library & Templates
        Schema::create('ctr_clause_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique(); // e.g. CL-FORCE-MAJEURE, CL-CONFIDENTIALITY
            $table->string('title');
            $table->string('category')->default('general'); // general, payment, liability, confidentiality, termination, jurisdiction
            $table->text('body_template'); // Content with {{variable}} placeholders
            $table->integer('version')->default(1);
            $table->boolean('is_standard')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ctr_contract_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique(); // e.g. TPL-PURCHASE-STD, TPL-LOGISTICS-SLA
            $table->string('name');
            $table->string('contract_type'); // purchase, sale, distribution, agency, lease, service, etc.
            $table->text('description')->nullable();
            $table->json('default_clause_ids')->nullable(); // array of clause_template UUIDs in order
            $table->json('required_variables')->nullable(); // list of placeholder keys
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Master Kontrak (ctr_contracts)
        Schema::create('ctr_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('contract_number')->unique()->comment('Gapless number via Core DocumentNumbering');
            $table->string('title');
            $table->string('contract_type'); // purchase, sale, distribution, etc.
            $table->string('status')->default('draft'); // draft, review, negotiation, approved, signed, active, suspended, expired, terminated, renewed
            $table->uuid('legal_entity_id')->comment('Holding or subsidiary owning contract');
            $table->uuid('template_id')->nullable();
            $table->bigInteger('total_value_idr')->default(0)->comment('Brick integer Rupiah');
            $table->string('currency', 3)->default('IDR');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('notice_period_days')->default(30);
            $table->boolean('auto_renew')->default(false);
            $table->integer('renewal_period_months')->nullable();
            $table->string('governing_law')->default('Indonesia');
            $table->string('dispute_forum')->default('BANI Jakarta');
            $table->text('current_body')->nullable();
            $table->string('current_hash', 64)->nullable()->comment('Latest hash-chain digest');
            $table->foreignId('approval_id')->nullable()->constrained('core_approvals')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->text('termination_reason')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->uuid('renewed_to_id')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('legal_entity_id')->references('id')->on('pty_legal_entities')->cascadeOnDelete();
            $table->foreign('template_id')->references('id')->on('ctr_contract_templates')->nullOnDelete();
            $table->index(['status', 'contract_type']);
            $table->index(['end_date', 'status']);
        });

        // 3. Pihak Kontrak (≥ 2 pihak)
        Schema::create('ctr_contract_parties', function (Blueprint $table) {
            $table->id();
            $table->uuid('contract_id');
            $table->uuid('party_id');
            $table->string('role'); // first_party, second_party, guarantor, witness, third_party
            $table->integer('signing_order')->default(1);
            $table->boolean('is_signed')->default(false);
            $table->timestamp('signed_at')->nullable();
            $table->string('signature_hash', 64)->nullable()->comment('Simulated digital signature hash');
            $table->string('signer_name')->nullable();
            $table->string('signer_title')->nullable();
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('ctr_contracts')->cascadeOnDelete();
            $table->foreign('party_id')->references('id')->on('pty_parties')->cascadeOnDelete();
            $table->unique(['contract_id', 'party_id', 'role']);
        });

        // 4. Versioning Hash-Chain Append-Only (ctr_contract_versions)
        Schema::create('ctr_contract_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('contract_id');
            $table->integer('sequence');
            $table->string('change_type'); // creation, amendment, negotiation, clause_update
            $table->text('body');
            $table->json('metadata')->nullable();
            $table->string('prev_hash', 80)->comment('hash versi sebelumnya (64) atau penanda genesis GENESIS_CTR_… (72)');
            $table->string('hash', 64);
            $table->string('created_by_name')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('contract_id')->references('id')->on('ctr_contracts')->cascadeOnDelete();
            $table->unique(['contract_id', 'sequence']);
        });

        // 5. Obligasi & Milestones (ctr_milestones)
        Schema::create('ctr_milestones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('contract_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date');
            $table->string('responsible_role')->default('second_party');
            $table->string('status')->default('pending'); // pending, in_progress, completed, overdue, waived
            $table->timestamp('completed_at')->nullable();
            $table->string('completion_proof_url')->nullable();
            $table->text('completion_notes')->nullable();
            $table->bigInteger('amount_idr')->default(0)->comment('Optional milestone payment trigger');
            $table->boolean('reminder_sent')->default(false);
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('ctr_contracts')->cascadeOnDelete();
            $table->index(['due_date', 'status']);
        });

        // 6. Klausul Kontrak Terpasang (ctr_contract_clauses)
        Schema::create('ctr_contract_clauses', function (Blueprint $table) {
            $table->id();
            $table->uuid('contract_id');
            $table->uuid('clause_template_id')->nullable();
            $table->integer('display_order')->default(1);
            $table->string('title');
            $table->text('body');
            $table->boolean('is_negotiated')->default(false);
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('ctr_contracts')->cascadeOnDelete();
            $table->foreign('clause_template_id')->references('id')->on('ctr_clause_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ctr_contract_clauses');
        Schema::dropIfExists('ctr_milestones');
        Schema::dropIfExists('ctr_contract_versions');
        Schema::dropIfExists('ctr_contract_parties');
        Schema::dropIfExists('ctr_contracts');
        Schema::dropIfExists('ctr_contract_templates');
        Schema::dropIfExists('ctr_clause_templates');
    }
};
