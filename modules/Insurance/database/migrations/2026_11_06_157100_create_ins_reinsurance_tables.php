<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ins_request_keys', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 120)->unique();
            $table->string('scope', 80);
            $table->string('payload_hash', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('ins_parameters', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80);
            $table->unsignedInteger('version');
            $table->date('effective_from');
            $table->string('source', 200);
            $table->string('simulation_label', 8)->default('S1');
            $table->json('value');
            $table->boolean('is_fixture')->default(false);
            $table->timestamp('owner_confirmed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['code', 'version']);
            $table->index(['code', 'effective_from']);
        });

        Schema::table('ins_full_policies', function (Blueprint $table) {
            $table->string('region_code', 24)->nullable()->after('customer_party_ref');
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->index('region_code');
        });

        Schema::table('ins_full_claims', function (Blueprint $table) {
            $table->string('cat_event_code', 40)->nullable()->after('incident_description');
            $table->index('cat_event_code');
        });

        Schema::create('ins_rein_treaties', function (Blueprint $table) {
            $table->id();
            $table->string('treaty_code', 40)->unique();
            $table->string('type', 24);
            $table->string('name', 160);
            $table->string('line_category', 32)->nullable();
            $table->string('status', 16)->default('draft');
            $table->date('inception_date');
            $table->date('expiry_date');
            $table->unsignedSmallInteger('quota_share_bps')->default(0);
            $table->unsignedBigInteger('surplus_retention_idr')->default(0);
            $table->unsignedSmallInteger('surplus_lines')->default(0);
            $table->unsignedBigInteger('attachment_idr')->default(0);
            $table->unsignedBigInteger('limit_idr')->default(0);
            $table->json('reinstatement_rates_bps')->nullable();
            $table->unsignedBigInteger('layer_premium_idr')->default(0);
            $table->unsignedSmallInteger('commission_bps')->default(0);
            $table->unsignedSmallInteger('sl_attachment_lr_bps')->default(0);
            $table->unsignedSmallInteger('sl_limit_lr_bps')->default(0);
            $table->string('pending_status', 16)->nullable();
            $table->unsignedBigInteger('approval_ref')->nullable();
            $table->unsignedBigInteger('premium_booked_idr')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'line_category']);
            $table->index(['type', 'inception_date']);
        });

        Schema::create('ins_rein_treaty_panel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treaty_id')->constrained('ins_rein_treaties')->cascadeOnDelete();
            $table->uuid('reinsurer_party_id');
            $table->unsignedSmallInteger('share_bps');
            $table->timestamps();

            $table->foreign('reinsurer_party_id')->references('id')->on('pty_parties')->restrictOnDelete();
            $table->unique(['treaty_id', 'reinsurer_party_id']);
        });

        Schema::create('ins_rein_treaty_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treaty_id')->constrained('ins_rein_treaties')->cascadeOnDelete();
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);
            $table->string('reason', 500)->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('approval_ref')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['treaty_id', 'occurred_at']);
        });

        Schema::create('ins_cession_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 120)->unique();
            $table->string('kind', 20);
            $table->foreignId('policy_id')->nullable()->constrained('ins_full_policies')->restrictOnDelete();
            $table->foreignId('claim_id')->nullable()->constrained('ins_full_claims')->restrictOnDelete();
            $table->foreignId('treaty_id')->nullable()->constrained('ins_rein_treaties')->restrictOnDelete();
            $table->string('period_ym', 7);
            $table->unsignedBigInteger('gross_idr');
            $table->unsignedBigInteger('retained_idr');
            $table->unsignedBigInteger('ceded_idr');
            $table->unsignedBigInteger('commission_idr')->default(0);
            $table->unsignedBigInteger('reinstatement_idr')->default(0);
            $table->json('snapshot');
            $table->string('snapshot_hash', 64);
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('bank_ledger_transactions')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['period_ym', 'kind']);
            $table->index('policy_id');
            $table->index('claim_id');
        });

        Schema::create('ins_cession_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('ins_cession_events')->restrictOnDelete();
            $table->foreignId('treaty_id')->constrained('ins_rein_treaties')->restrictOnDelete();
            $table->string('layer_code', 24);
            $table->uuid('reinsurer_party_id');
            $table->unsignedBigInteger('ceded_idr');
            $table->unsignedBigInteger('commission_idr')->default(0);
            $table->unsignedBigInteger('reinstatement_idr')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('reinsurer_party_id')->references('id')->on('pty_parties')->restrictOnDelete();
            $table->unique(['event_id', 'treaty_id', 'layer_code', 'reinsurer_party_id'], 'ins_cession_lines_unique');
            $table->index(['reinsurer_party_id', 'treaty_id']);
        });

        Schema::create('ins_rein_bordereaux', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('treaty_id')->constrained('ins_rein_treaties')->restrictOnDelete();
            $table->uuid('reinsurer_party_id');
            $table->string('kind', 16);
            $table->string('period_ym', 7);
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('line_count')->default(0);
            $table->bigInteger('total_ceded_idr')->default(0);
            $table->bigInteger('total_commission_idr')->default(0);
            $table->bigInteger('total_reinstatement_idr')->default(0);
            $table->foreignId('reverses_id')->nullable()->constrained('ins_rein_bordereaux')->restrictOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('reinsurer_party_id')->references('id')->on('pty_parties')->restrictOnDelete();
            $table->index(['treaty_id', 'reinsurer_party_id', 'kind', 'period_ym'], 'ins_bordereaux_scope_idx');
            $table->index('status');
        });

        Schema::create('ins_rein_bordereau_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bordereau_id')->constrained('ins_rein_bordereaux')->restrictOnDelete();
            $table->foreignId('cession_line_id')->constrained('ins_cession_lines')->restrictOnDelete();
            $table->bigInteger('amount_idr');
            $table->bigInteger('commission_idr')->default(0);
            $table->bigInteger('reinstatement_idr')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['bordereau_id', 'cession_line_id']);
        });

        Schema::create('ins_rein_account_currents', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->uuid('reinsurer_party_id');
            $table->string('period_ym', 7);
            $table->bigInteger('premium_ceded_idr')->default(0);
            $table->bigInteger('commission_idr')->default(0);
            $table->bigInteger('claims_recoverable_idr')->default(0);
            $table->bigInteger('reinstatement_idr')->default(0);
            $table->bigInteger('net_due_idr')->default(0);
            $table->string('status', 16)->default('open');
            $table->foreignId('settlement_ledger_tx')->nullable()->constrained('bank_ledger_transactions')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('reinsurer_party_id')->references('id')->on('pty_parties')->restrictOnDelete();
            $table->unique(['reinsurer_party_id', 'period_ym']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ins_rein_account_currents');
        Schema::dropIfExists('ins_rein_bordereau_lines');
        Schema::dropIfExists('ins_rein_bordereaux');
        Schema::dropIfExists('ins_cession_lines');
        Schema::dropIfExists('ins_cession_events');
        Schema::dropIfExists('ins_rein_treaty_history');
        Schema::dropIfExists('ins_rein_treaty_panel');
        Schema::dropIfExists('ins_rein_treaties');
        Schema::table('ins_full_claims', function (Blueprint $table) {
            $table->dropIndex(['cat_event_code']);
            $table->dropColumn('cat_event_code');
        });
        Schema::table('ins_full_policies', function (Blueprint $table) {
            $table->dropIndex(['region_code']);
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn(['region_code', 'idempotency_key']);
        });
        Schema::dropIfExists('ins_parameters');
        Schema::dropIfExists('ins_request_keys');
    }
};
