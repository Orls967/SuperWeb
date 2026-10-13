<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 29.1 Jadwal pembayaran: termin, milestone, berkala (+retensi & uang muka) ──
        Schema::create('ctr_payment_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('contract_id');
            $table->uuid('milestone_id')->nullable()->comment('Tautan ke milestone bila termin terikat deliverable');
            $table->string('kind', 32)->default('term')->comment('advance, term, periodic, milestone, retention');
            $table->date('due_date');
            $table->bigInteger('amount_idr')->default(0)->comment('Nilai termin sebelum retensi');
            $table->bigInteger('retention_amount_idr')->default(0)->comment('Potongan retensi pada termin ini');
            $table->bigInteger('paid_amount_idr')->default(0);
            $table->string('status', 32)->default('pending')->comment('pending, partial, paid, waived');
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('ctr_contracts')->cascadeOnDelete();
            $table->foreign('milestone_id')->references('id')->on('ctr_milestones')->nullOnDelete();
            $table->index(['status', 'due_date']);
            $table->index(['contract_id', 'due_date']);
        });

        // ── 29.3 Indeks harga tersimpan untuk eskalasi ──
        Schema::create('ctr_escalation_indexes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->comment('Mis. CPI, FUEL, FX_USD_IDR');
            $table->string('name', 120);
            $table->decimal('value', 18, 6)->comment('Nilai indeks');
            $table->date('observed_at');
            $table->string('source')->default('simulasi');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['code', 'observed_at']);
            $table->index('code');
        });

        // ── 29.2 Aturan denda / liquidated damages + pembebasan ──
        Schema::create('ctr_penalty_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('contract_id')->nullable()->comment('NULL = aturan global platform');
            $table->string('name', 120);
            $table->string('unit', 24)->comment('per_day_fixed, percent_per_day');
            $table->bigInteger('value')->default(0)->comment('Rupiah/hari, atau basis point per hari (bp: 100 = 1%)');
            $table->bigInteger('cap_amount_idr')->nullable()->comment('Plafon akumulasi denda');
            $table->integer('grace_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('ctr_contracts')->cascadeOnDelete();
            $table->index(['contract_id', 'is_active']);
        });

        // ── 29.4 Amandemen & addendum: jejak lengkap perubahan ──
        Schema::create('ctr_amendments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('contract_id');
            $table->uuid('contract_version_id')->nullable()->comment('Versi hash-chain hasil amandemen');
            $table->string('kind', 32)->default('amendment')->comment('amendment, addendum');
            $table->json('changes')->comment('Field yang berubah: old/new per kunci');
            $table->bigInteger('old_value_idr')->nullable();
            $table->bigInteger('new_value_idr')->nullable();
            $table->date('old_end_date')->nullable();
            $table->date('new_end_date')->nullable();
            $table->date('effective_date');
            $table->boolean('schedule_recalculated')->default(false);
            $table->text('reason')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('ctr_contracts')->cascadeOnDelete();
            $table->foreign('contract_version_id')->references('id')->on('ctr_contract_versions')->nullOnDelete();
            $table->index(['contract_id', 'effective_date']);
        });

        // ── 29.5 Rekonsiliasi pemakaian plafon vs transaksi riil ──
        Schema::create('ctr_usage_ledger', function (Blueprint $table) {
            $table->id();
            $table->uuid('contract_id');
            $table->string('source_type', 40)->comment('po, sale, shipment, lease_billing, royalty');
            $table->unsignedBigInteger('source_id')->comment('ID baris transaksi sumber');
            $table->bigInteger('amount_idr')->default(0);
            $table->timestamp('occurred_at');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('ctr_contracts')->cascadeOnDelete();
            $table->unique(['source_type', 'source_id']);
            $table->index(['contract_id', 'occurred_at']);
        });

        // ── Kolom tambahan kontrak: keuangan (29.1), eskalasi (29.3), kepatuhan (29.7), integrasi (29.6) ──
        Schema::table('ctr_contracts', function (Blueprint $table) {
            $table->bigInteger('advance_amount_idr')->default(0)->comment('Uang muka kontrak');
            $table->bigInteger('advance_paid_idr')->default(0);
            $table->unsignedInteger('retention_percent')->default(0)->comment('Retensi (%), 0-100');
            $table->boolean('escalation_enabled')->default(false);
            $table->string('escalation_formula')->nullable()->comment('Mis. base * (1 + 0.5 * (index/index_base - 1))');
            $table->string('escalation_index_code', 40)->nullable();
            $table->decimal('escalation_index_base', 18, 6)->nullable()->comment('Nilai indeks dasar saat kontrak ditandatangani');
            $table->decimal('escalation_cap_percent', 8, 2)->nullable()->comment('Plafon kenaikan per periode');
            $table->string('arbitration_rules', 40)->nullable()->comment('BANI, ICC, SIAC — data referensi simulasi');
            $table->unsignedTinyInteger('risk_score')->default(0)->comment('Skor risiko 0-100 (aturan simulasi)');
            $table->json('risk_flags')->nullable()->comment('Kumpulan flag risiko');
            $table->bigInteger('used_value_idr')->default(0)->comment('Agregat nilai terpakai (cache dari ctr_usage_ledger)');
            $table->unsignedBigInteger('linked_rate_card_id')->nullable()->comment('lgx_rate_cards.id — link non-breaking (tanpa FK, akses via contract/event)');
            $table->uuid('linked_lease_id')->nullable()->comment('mall_leases.id — link non-breaking, bukan duplikasi');
            $table->uuid('linked_royalty_ref')->nullable()->comment('Referensi kontrak waralaba Resto');
        });
    }

    public function down(): void
    {
        Schema::table('ctr_contracts', function (Blueprint $table) {
            $table->dropColumn([
                'advance_amount_idr', 'advance_paid_idr', 'retention_percent',
                'escalation_enabled', 'escalation_formula', 'escalation_index_code',
                'escalation_index_base', 'escalation_cap_percent', 'arbitration_rules',
                'risk_score', 'risk_flags', 'used_value_idr',
                'linked_rate_card_id', 'linked_lease_id', 'linked_royalty_ref',
            ]);
        });

        Schema::dropIfExists('ctr_usage_ledger');
        Schema::dropIfExists('ctr_amendments');
        Schema::dropIfExists('ctr_penalty_rules');
        Schema::dropIfExists('ctr_escalation_indexes');
        Schema::dropIfExists('ctr_payment_schedules');
    }
};
