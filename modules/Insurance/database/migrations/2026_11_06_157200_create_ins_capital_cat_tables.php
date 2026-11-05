<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ins_solv_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('snapshot_key', 80)->unique();
            $table->date('valuation_date');
            $table->json('parameter_versions');
            $table->json('risk_charges');
            $table->json('correlation');
            $table->unsignedBigInteger('operational_idr');
            $table->unsignedBigInteger('available_capital_idr');
            $table->unsignedBigInteger('rbc_idr');
            $table->unsignedInteger('ratio_bps');
            $table->string('alert_level', 16);
            $table->string('input_hash', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('valuation_date');
        });

        Schema::create('ins_solv_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('ins_solv_snapshots')->restrictOnDelete();
            $table->string('action_type', 24);
            $table->json('params');
            $table->unsignedInteger('simulated_ratio_bps');
            $table->string('status', 20)->default('proposed');
            $table->unsignedBigInteger('approval_ref')->nullable();
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('bank_ledger_transactions')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['snapshot_id', 'status']);
        });

        Schema::create('ins_cat_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_code', 60)->unique();
            $table->string('portfolio_code', 40);
            $table->unsignedInteger('seed');
            $table->unsignedInteger('years');
            $table->unsignedInteger('events_per_peril');
            $table->json('parameter_versions');
            $table->string('catalog_hash', 64)->nullable();
            $table->string('exposure_hash', 64)->nullable();
            $table->unsignedBigInteger('total_exposure_idr')->default(0);
            $table->unsignedBigInteger('aal_idr')->default(0);
            $table->unsignedBigInteger('aep_rp100_idr')->default(0);
            $table->unsignedBigInteger('aep_rp250_idr')->default(0);
            $table->unsignedBigInteger('oep_rp100_idr')->default(0);
            $table->unsignedBigInteger('tvar_rp100_idr')->default(0);
            $table->string('status', 16)->default('queued');
            $table->text('failure_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['portfolio_code', 'status']);
        });

        Schema::create('ins_cat_exposures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('ins_cat_runs')->cascadeOnDelete();
            $table->string('region_code', 24);
            $table->string('peril', 24);
            $table->unsignedBigInteger('exposure_idr');
            $table->unsignedInteger('policy_count');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['run_id', 'region_code', 'peril']);
        });

        Schema::create('ins_cat_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('ins_cat_runs')->cascadeOnDelete();
            $table->unsignedInteger('event_no');
            $table->string('peril', 24);
            $table->string('region_code', 24);
            $table->unsignedSmallInteger('intensity_bps');
            $table->unsignedInteger('annual_rate_ppm');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['run_id', 'event_no']);
            $table->index(['run_id', 'peril']);
        });

        Schema::create('ins_cat_year_losses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('ins_cat_runs')->cascadeOnDelete();
            $table->unsignedInteger('year_index');
            $table->unsignedBigInteger('aggregate_idr');
            $table->unsignedBigInteger('max_event_idr');
            $table->json('events');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['run_id', 'year_index']);
        });

        Schema::create('ins_cat_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('ins_cat_runs')->cascadeOnDelete();
            $table->string('structure_code', 40);
            $table->unsignedBigInteger('attachment_idr');
            $table->unsignedBigInteger('limit_idr');
            $table->unsignedSmallInteger('reinstatements')->default(0);
            $table->unsignedBigInteger('layer_premium_idr');
            $table->unsignedBigInteger('ceded_aal_idr');
            $table->unsignedBigInteger('net_pml_rp100_idr');
            $table->unsignedBigInteger('net_pml_rp250_idr');
            $table->bigInteger('net_cost_idr');
            $table->timestamps();

            $table->unique(['run_id', 'structure_code']);
        });

        Schema::create('ins_cat_bonds', function (Blueprint $table) {
            $table->id();
            $table->string('bond_code', 40)->unique();
            $table->string('asset_code', 24)->unique();
            $table->foreignId('rwa_asset_id')->nullable()->constrained('rwa_assets')->nullOnDelete();
            $table->unsignedBigInteger('principal_idr');
            $table->unsignedBigInteger('outstanding_principal_idr');
            $table->unsignedInteger('units');
            $table->unsignedSmallInteger('spread_bps');
            $table->unsignedBigInteger('attachment_idr');
            $table->unsignedBigInteger('exhaustion_idr');
            $table->foreignId('cat_run_id')->nullable()->constrained('ins_cat_runs')->nullOnDelete();
            $table->string('status', 16)->default('draft');
            $table->date('issue_date')->nullable();
            $table->date('maturity_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('ins_cat_bond_coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bond_id')->constrained('ins_cat_bonds')->restrictOnDelete();
            $table->string('quarter_key', 7);
            $table->unsignedBigInteger('outstanding_idr');
            $table->unsignedBigInteger('total_idr');
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('bank_ledger_transactions')->nullOnDelete();
            $table->timestamp('paid_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['bond_id', 'quarter_key']);
        });

        Schema::create('ins_cat_bond_coupon_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('ins_cat_bond_coupons')->restrictOnDelete();
            $table->foreignId('holder_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('units');
            $table->unsignedBigInteger('amount_idr');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['coupon_id', 'holder_user_id']);
        });

        Schema::create('ins_cat_bond_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bond_id')->constrained('ins_cat_bonds')->restrictOnDelete();
            $table->string('event_key', 80)->unique();
            $table->unsignedBigInteger('industry_loss_idr');
            $table->unsignedBigInteger('reduction_idr');
            $table->unsignedBigInteger('outstanding_after_idr');
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('bank_ledger_transactions')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ins_cat_bond_events');
        Schema::dropIfExists('ins_cat_bond_coupon_payouts');
        Schema::dropIfExists('ins_cat_bond_coupons');
        Schema::dropIfExists('ins_cat_bonds');
        Schema::dropIfExists('ins_cat_plans');
        Schema::dropIfExists('ins_cat_year_losses');
        Schema::dropIfExists('ins_cat_events');
        Schema::dropIfExists('ins_cat_exposures');
        Schema::dropIfExists('ins_cat_runs');
        Schema::dropIfExists('ins_solv_actions');
        Schema::dropIfExists('ins_solv_snapshots');
    }
};
