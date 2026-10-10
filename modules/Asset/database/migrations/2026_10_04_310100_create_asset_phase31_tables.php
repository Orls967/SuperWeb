<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ast_assets', function (Blueprint $table) {
            $table->bigInteger('salvage_value_idr')->default(0)->after('accumulated_depreciation_idr');
        });

        // 31.1 Penyusutan: catatan per (aset, periode, metode) — idempoten.
        Schema::create('ast_depreciations', function (Blueprint $table) {
            $table->id();
            $table->uuid('asset_id');
            $table->string('period', 7)->comment('YYYY-MM');
            $table->string('method', 24)->comment('straight_line, declining_balance, units_of_production, fiscal');
            $table->string('book', 16)->default('commercial')->comment('commercial | fiscal (31.2 dua buku)');
            $table->bigInteger('amount_idr')->default(0);
            $table->bigInteger('accumulated_after_idr')->default(0);
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->text('meta')->nullable();
            $table->timestamps();

            $table->foreign('asset_id')->references('id')->on('ast_assets')->cascadeOnDelete();
            $table->unique(['asset_id', 'period', 'method', 'book']);
            $table->index(['period', 'book']);
        });

        // 31.1 Unit produksi: jam mesin / km menumpuk per aset.
        Schema::create('ast_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('asset_id');
            $table->date('logged_at');
            $table->bigInteger('units')->default(0)->comment('Cumulative jam/km sejak perolehan');
            $table->string('unit_type', 16)->default('hours')->comment('hours | km');
            $table->string('source')->default('manual');
            $table->timestamps();

            $table->foreign('asset_id')->references('id')->on('ast_assets')->cascadeOnDelete();
            $table->index(['asset_id', 'logged_at']);
        });

        // 31.3 Revaluasi & impairment (butuh approval sebelum posting).
        Schema::create('ast_revaluations', function (Blueprint $table) {
            $table->id();
            $table->uuid('asset_id');
            $table->string('kind', 16)->comment('revaluation | impairment');
            $table->bigInteger('old_value_idr')->default(0);
            $table->bigInteger('new_value_idr')->default(0);
            $table->bigInteger('difference_idr')->default(0)->comment('Selisih; surplus di ekuitas, defisit di beban');
            $table->string('reason')->nullable();
            $table->string('approval_id', 64)->nullable();
            $table->string('approval_status', 16)->default('pending')->comment('pending, approved, rejected');
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('asset_id')->references('id')->on('ast_assets')->cascadeOnDelete();
            $table->index(['approval_status', 'kind']);
        });

        // 31.4 Disposal: jual, hapus, hibah, hilang (four-eyes via approval).
        Schema::create('ast_disposals', function (Blueprint $table) {
            $table->id();
            $table->uuid('asset_id');
            $table->string('method', 16)->comment('sale, write_off, donation, loss');
            $table->bigInteger('proceeds_idr')->default(0);
            $table->bigInteger('book_value_at_disposal_idr')->default(0);
            $table->bigInteger('gain_loss_idr')->default(0)->comment('Proceeds − book value; + = gain, − = loss');
            $table->string('reason')->nullable();
            $table->string('approval_id', 64)->nullable();
            $table->string('approval_status', 16)->default('pending');
            $table->string('source_type', 32)->nullable()->comment('store_order (31.4 link)');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamp('disposed_at')->nullable();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('asset_id')->references('id')->on('ast_assets')->cascadeOnDelete();
            $table->index(['approval_status', 'method']);
        });

        // 31.5 Work order aset (generalisasi dari Mall WO / AutoServe fleet service).
        Schema::create('ast_work_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('asset_id');
            $table->string('number', 40)->unique()->comment('Nomor WO via DocumentNumbering');
            $table->string('type', 20)->default('preventive')->comment('preventive, corrective, calibration');
            $table->string('trigger', 20)->default('time')->comment('time | usage (berbasis jam/km)');
            $table->string('status', 20)->default('scheduled')->comment('scheduled, in_progress, completed, cancelled');
            $table->date('due_date')->nullable()->comment('Untuk trigger time');
            $table->unsignedBigInteger('due_units')->nullable()->comment('Untuk trigger usage (jam/km kumulatif)');
            $table->unsignedBigInteger('completed_units')->nullable();
            $table->date('completed_at')->nullable();
            $table->bigInteger('parts_cost_idr')->default(0);
            $table->bigInteger('labor_cost_idr')->default(0);
            $table->bigInteger('total_cost_idr')->default(0);
            $table->string('cost_treatment', 16)->default('expense')->comment('expense | capitalized (menambah biaya aset)');
            $table->string('description')->nullable();
            $table->string('vendor')->nullable();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->timestamps();

            $table->foreign('asset_id')->references('id')->on('ast_assets')->cascadeOnDelete();
            $table->index(['status', 'due_date']);
            $table->index(['asset_id', 'trigger']);
        });

        // 31.6 Sewa (PSAK 73 simulasi): hak guna + liabilitas sewa dari kontrak Fase 29.
        Schema::create('ast_leases', function (Blueprint $table) {
            $table->id();
            $table->uuid('asset_id');
            $table->uuid('contract_id')->nullable()->comment('Tautan ke ctr_contracts (Fase 29)');
            $table->string('lease_kind', 16)->default('finance')->comment('finance (hak guna) | operating');
            $table->bigInteger('right_of_use_asset_idr')->default(0)->comment('Hak guna aset');
            $table->bigInteger('lease_liability_idr')->default(0)->comment('Liabilitas sewa');
            $table->bigInteger('periodic_payment_idr')->default(0);
            $table->unsignedSmallInteger('total_periods')->default(0);
            $table->unsignedSmallInteger('elapsed_periods')->default(0);
            $table->bigInteger('amortized_interest_idr')->default(0);
            $table->decimal('implicit_rate', 8, 4)->default(0)->nullable()->comment('Suku bunga implisit %/periode (simulasi)');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->foreign('asset_id')->references('id')->on('ast_assets')->cascadeOnDelete();
            $table->index(['status', 'end_date']);
        });

        // 31.6 Pembayaran/periode amortisasi sewa.
        Schema::create('ast_lease_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lease_id');
            $table->unsignedSmallInteger('period_no');
            $table->date('due_date');
            $table->bigInteger('payment_idr')->default(0);
            $table->bigInteger('interest_portion_idr')->default(0);
            $table->bigInteger('principal_portion_idr')->default(0);
            $table->string('status', 16)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('lease_id')->references('id')->on('ast_leases')->cascadeOnDelete();
            $table->unique(['lease_id', 'period_no']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('ast_assets', function (Blueprint $table) {
            $table->dropColumn('salvage_value_idr');
        });
        Schema::dropIfExists('ast_lease_payments');
        Schema::dropIfExists('ast_leases');
        Schema::dropIfExists('ast_work_orders');
        Schema::dropIfExists('ast_disposals');
        Schema::dropIfExists('ast_revaluations');
        Schema::dropIfExists('ast_usage_logs');
        Schema::dropIfExists('ast_depreciations');
    }
};
