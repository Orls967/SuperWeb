<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Persetujuan keringanan covenant (Covenant Waiver — D48-7 & D48-11e)
        Schema::create('trs_covenant_waivers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('facility_id')->index('trs_cov_waiv_fac_idx');
            $table->string('waiver_code', 50)->unique('trs_cov_waiv_code_uq');
            $table->unsignedInteger('breached_der_permil');
            $table->unsignedInteger('waived_max_der_permil');
            $table->date('valid_until');
            $table->string('status', 20)->default('pending')->index('trs_cov_waiv_stat_idx'); // pending, approved, rejected, expired
            $table->string('approval_reference', 80)->nullable();
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();

            $table->foreign('facility_id', 'trs_cov_waiv_fac_fk')
                ->references('id')
                ->on('trs_credit_facilities')
                ->cascadeOnDelete();
        });

        // 2. Pengisian kembali kas kecil (Imprest Replenishments & Cash Count Discrepancies — D48-5)
        Schema::create('trs_petty_cash_replenishments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('bank_account_id')->index('trs_pcr_bank_idx');
            $table->string('replenishment_number', 50)->unique('trs_pcr_num_uq');
            $table->date('replenishment_date');
            $table->bigInteger('imprest_limit_idr');
            $table->bigInteger('book_balance_before_idr');
            $table->bigInteger('physical_cash_counted_idr');
            $table->bigInteger('shortage_overage_idr')->default(0);
            $table->bigInteger('replenished_amount_idr');
            $table->bigInteger('balance_after_idr');
            $table->string('source_bank_account_code', 80)->default('trs:bank_operational:HOLDING:IDR');
            $table->foreignId('ledger_tx_id')->nullable()->constrained('bank_ledger_transactions')->nullOnDelete();
            $table->string('status', 20)->default('completed');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();

            $table->foreign('bank_account_id', 'trs_pcr_bank_fk')
                ->references('id')
                ->on('trs_bank_accounts')
                ->cascadeOnDelete();
        });

        // 3. Batch impor mutasi rekening koran CSV / MT940 idempoten (D48-5)
        Schema::create('trs_statement_import_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('bank_account_id')->index('trs_sib_bank_idx');
            $table->string('batch_reference', 64)->unique('trs_sib_ref_uq');
            $table->string('format', 16)->default('MT940'); // CSV, MT940
            $table->string('payload_hash', 64)->index('trs_sib_hash_idx');
            $table->unsignedInteger('total_lines')->default(0);
            $table->unsignedInteger('imported_lines')->default(0);
            $table->unsignedInteger('skipped_duplicate_lines')->default(0);
            $table->bigInteger('opening_balance_minor')->default(0);
            $table->bigInteger('closing_balance_minor')->default(0);
            $table->unsignedBigInteger('imported_by')->nullable();
            $table->timestamps();

            $table->foreign('bank_account_id', 'trs_sib_bank_fk')
                ->references('id')
                ->on('trs_bank_accounts')
                ->cascadeOnDelete();
        });

        // 4. Kolom umur (aging_days) dan audit unmatch pada antrean rekonsiliasi & mutasi bank
        Schema::table('trs_bank_reconciliation_unmatched', function (Blueprint $table) {
            $table->unsignedInteger('aging_days')->default(0)->after('status');
            $table->string('escalation_task_id', 64)->nullable()->after('aging_days');
            $table->index('escalation_task_id', 'trs_bru_esc_task_idx');
        });

        Schema::table('trs_bank_statements', function (Blueprint $table) {
            $table->string('unmatch_reason', 255)->nullable()->after('reconciliation_status');
            $table->unsignedBigInteger('unmatched_by')->nullable()->after('unmatch_reason');
            $table->timestamp('unmatched_at')->nullable()->after('unmatched_by');
        });
    }

    public function down(): void
    {
        Schema::table('trs_bank_statements', function (Blueprint $table) {
            $table->dropColumn(['unmatch_reason', 'unmatched_by', 'unmatched_at']);
        });

        Schema::table('trs_bank_reconciliation_unmatched', function (Blueprint $table) {
            $table->dropColumn(['aging_days', 'escalation_task_id']);
        });

        Schema::dropIfExists('trs_statement_import_batches');
        Schema::dropIfExists('trs_petty_cash_replenishments');
        Schema::dropIfExists('trs_covenant_waivers');
    }
};
