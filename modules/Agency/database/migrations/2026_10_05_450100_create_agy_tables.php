<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 45.1–45.9 Agen: entitas & hirarki, komisi, atribusi, akrual/clawback,
// payout, statement, ledger.
return new class extends Migration
{
    public function up(): void
    {
        // 45.1 Agen individu/badan + tipe + hirarki upline/downline.
        Schema::create('agy_agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name', 180);
            $table->string('kind', 24)->default('sales_agent')->comment('sales_agent, broker, reseller, affiliate, sole_agent');
            $table->uuid('parent_id')->nullable()->comment('Upline');
            $table->uuid('party_id')->nullable()->comment('pty_parties.id (KYB)');
            $table->unsignedBigInteger('owner_user_id')->nullable()->comment('Portal role agent');
            $table->string('region_code', 40)->nullable();
            $table->string('status', 24)->default('onboarding')->comment('onboarding, active, suspended, terminated');
            $table->unsignedSmallInteger('max_downline_levels')->default(3)->comment('Maks override upline');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('agy_agents')->nullOnDelete();
            $table->index(['status', 'kind']);
        });

        // 45.2 Kontrak keagenan (tautan ringan ke ctr_contracts via string).
        Schema::create('agy_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agent_id');
            $table->string('contract_ref', 60)->nullable()->comment('ctr_contracts.id (query mentah)');
            $table->string('territory_scope', 60)->nullable();
            $table->json('product_scope')->nullable()->comment('SKU/produk eksklusif');
            $table->boolean('exclusive')->default(false);
            $table->boolean('non_compete')->default(false);
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->string('status', 16)->default('active')->comment('active, ended, terminated');
            $table->string('termination_reason', 300)->nullable();
            $table->timestamps();

            $table->foreign('agent_id')->references('id')->on('agy_agents')->cascadeOnDelete();
            $table->index(['status', 'valid_until']);
        });

        // 45.3 Skema komisi: flat / persentase / slab / bonus target.
        Schema::create('agy_commission_schemes', function (Blueprint $table) {
            $table->id();
            $table->uuid('agent_id');
            $table->string('code', 40)->comment('Aturan kode per agen');
            $table->string('name', 160);
            $table->string('basis', 16)->default('flat')->comment('flat, percent, slab, target_bonus');
            $table->bigInteger('flat_amount_idr')->default(0);
            $table->decimal('rate_percent', 8, 4)->default(0);
            $table->string('scope', 16)->default('all')->comment('all, sku, channel');
            $table->string('scope_ref', 60)->nullable();
            $table->json('slabs')->nullable()->comment('Slab: [{min_amount_idr, rate_percent}]');
            $table->bigInteger('target_amount_idr')->default(0)->comment('Untuk target_bonus');
            $table->bigInteger('bonus_amount_idr')->default(0);
            $table->unsignedTinyInteger('level')->default(0)->comment('0=agen, 1+=override upline');
            $table->decimal('override_rate_percent', 8, 4)->default(0)->comment('Rate override per level');
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('agent_id')->references('id')->on('agy_agents')->cascadeOnDelete();
            $table->index(['level', 'is_active']);
        });

        // 45.4 Atribusi penjualan: kode/referral + masa + prioritas konflik.
        Schema::create('agy_attributions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agent_id');
            $table->string('source', 16)->default('referral')->comment('referral, lead, agent_code');
            $table->string('reference_id', 60)->comment('order/user id sumber');
            $table->string('rule', 16)->default('last_touch')->comment('last_touch, first_touch');
            $table->date('touched_at');
            $table->date('expires_at')->nullable()->comment('Masa atribusi');
            $table->string('status', 16)->default('active')->comment('active, converted, expired');
            $table->timestamps();

            $table->foreign('agent_id')->references('id')->on('agy_agents')->cascadeOnDelete();
            $table->unique(['reference_id', 'agent_id']);
            $table->index(['reference_id', 'status']);
        });

        // 45.5/45.6 Akrual komisi + hold periode retur + clawback.
        Schema::create('agy_commission_accruals', function (Blueprint $table) {
            $table->id();
            $table->uuid('agent_id');
            $table->string('reference_id', 60)->comment('Order/penjualan sumber');
            $table->string('source_type', 16)->default('sale')->comment('sale, retur, payout_adjust');
            $table->date('accrued_at');
            $table->bigInteger('base_amount_idr')->default(0);
            $table->decimal('rate_percent', 8, 4)->default(0);
            $table->bigInteger('amount_idr')->default(0)->comment('Negatif = clawback');
            $table->string('status', 16)->default('hold')->comment('hold, payable, reversed, paid');
            $table->date('hold_until')->comment('Sampai periode retur lewat');
            $table->string('note', 300)->nullable();
            $table->timestamps();

            $table->foreign('agent_id')->references('id')->on('agy_agents')->cascadeOnDelete();
            $table->unique(['agent_id', 'reference_id', 'source_type']);
            $table->index(['status', 'hold_until']);
        });

        // 45.7 Payout periodik + PPh simulasi.
        Schema::create('agy_payouts', function (Blueprint $table) {
            $table->id();
            $table->uuid('agent_id');
            $table->string('number', 40)->unique()->comment('PAY/{ENT}/YYYY-NNNNN');
            $table->string('period', 8)->comment('YYYY atau YYYY-MM');
            $table->bigInteger('gross_idr')->default(0);
            $table->bigInteger('withheld_tax_idr')->default(0)->comment('PPh 23/21 SIMULASI');
            $table->bigInteger('net_idr')->default(0);
            $table->string('status', 16)->default('pending')->comment('pending, approved, paid, rejected');
            $table->unsignedBigInteger('approval_id')->nullable();
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->string('proof_reference', 80)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('agent_id')->references('id')->on('agy_agents')->cascadeOnDelete();
            $table->unique(['agent_id', 'period']);
            $table->index(['status', 'period']);
        });

        Schema::create('agy_payout_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payout_id');
            $table->unsignedBigInteger('accrual_id');
            $table->bigInteger('amount_idr')->default(0);
            $table->timestamps();

            $table->foreign('payout_id')->references('id')->on('agy_payouts')->cascadeOnDelete();
            $table->foreign('accrual_id')->references('id')->on('agy_commission_accruals')->cascadeOnDelete();
            $table->unique(['payout_id', 'accrual_id']);
        });

        // 45.8 Statement komisi (gabungan) + materi (opsional).
        Schema::create('agy_statements', function (Blueprint $table) {
            $table->id();
            $table->uuid('agent_id');
            $table->string('period', 8);
            $table->bigInteger('opening_balance_idr')->default(0);
            $table->bigInteger('accrued_idr')->default(0);
            $table->bigInteger('clawback_idr')->default(0);
            $table->bigInteger('paid_idr')->default(0);
            $table->bigInteger('closing_balance_idr')->default(0);
            $table->json('breakdown')->nullable();
            $table->timestamps();

            $table->foreign('agent_id')->references('id')->on('agy_agents')->cascadeOnDelete();
            $table->unique(['agent_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agy_statements');
        Schema::dropIfExists('agy_payout_items');
        Schema::dropIfExists('agy_payouts');
        Schema::dropIfExists('agy_commission_accruals');
        Schema::dropIfExists('agy_attributions');
        Schema::dropIfExists('agy_commission_schemes');
        Schema::dropIfExists('agy_contracts');
        Schema::dropIfExists('agy_agents');
    }
};
