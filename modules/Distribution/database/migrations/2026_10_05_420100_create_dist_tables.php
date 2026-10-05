<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 42.1–42.8 Jaringan distributor: entitas, teritori, onboarding/jaminan,
// piutang (AR), target/tier, outlet sell-out, scorecard.
return new class extends Migration
{
    public function up(): void
    {
        // 42.1 Entitas jaringan: distributor / sub-distributor / agen / dealer.
        if (! Schema::hasTable('dist_distributors')) {
            Schema::create('dist_distributors', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('code', 40)->unique();
                $table->string('name', 180);
                $table->string('kind', 24)->default('distributor')->comment('distributor, sub_distributor, agent, dealer');
                $table->uuid('parent_id')->nullable()->comment('hirarki jaringan → distributor induk');
                $table->uuid('party_id')->nullable()->comment('pty_parties.id (KYB Fase 27)');
                $table->unsignedBigInteger('owner_user_id')->nullable()->comment('Akun portal (role distributor)');
                $table->string('outlet_code', 40)->nullable()->comment('Kode toko/outlet');
                $table->string('tier', 8)->default('bronze')->comment('bronze, silver, gold');
                $table->string('status', 24)->default('onboarding')->comment('onboarding, approved, suspended, blocked, terminated');
                $table->unsignedInteger('payment_terms_days')->default(30);
                $table->bigInteger('credit_limit_idr')->default(0);
                $table->bigInteger('credit_exposure_idr')->default(0);
                $table->date('approved_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('parent_id')->references('id')->on('dist_distributors')->nullOnDelete();
                $table->index(['status', 'kind']);
                $table->index(['tier']);
            });
        }

        // 42.2 Teritori: provinsi → kota → kecamatan + coverage eksklusif.
        if (! Schema::hasTable('dist_territories')) {
            Schema::create('dist_territories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->string('code', 40)->unique();
                $table->string('name', 120);
                $table->string('level', 16)->default('city')->comment('province, city, district');
                $table->timestamps();

                $table->foreign('parent_id')->references('id')->on('dist_territories')->nullOnDelete();
                $table->index(['level', 'name']);
            });
        }

        if (! Schema::hasTable('dist_territory_coverage')) {
            Schema::create('dist_territory_coverage', function (Blueprint $table) {
                $table->id();
                $table->uuid('distributor_id');
                $table->unsignedBigInteger('territory_id');
                $table->boolean('exclusive')->default(false);
                $table->date('valid_from');
                $table->date('valid_until')->nullable();
                $table->timestamps();

                $table->foreign('distributor_id')->references('id')->on('dist_distributors')->cascadeOnDelete();
                $table->foreign('territory_id')->references('id')->on('dist_territories')->cascadeOnDelete();
                $table->unique(['distributor_id', 'territory_id']);
                $table->index(['territory_id', 'exclusive']);
            });
        }

        // 42.3 Jaminan onboarding: bank garansi / deposit.
        if (! Schema::hasTable('dist_securities')) {
            Schema::create('dist_securities', function (Blueprint $table) {
                $table->id();
                $table->uuid('distributor_id');
                $table->string('kind', 16)->comment('bank_guarantee, deposit');
                $table->bigInteger('amount_idr')->default(0);
                $table->string('reference', 80)->nullable();
                $table->date('issued_at')->nullable();
                $table->date('expires_at')->nullable();
                $table->string('status', 16)->default('active')->comment('active, claimed, returned, expired');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('distributor_id')->references('id')->on('dist_distributors')->cascadeOnDelete();
                $table->index(['kind', 'status']);
            });
        }

        // 42.4 Piutang distributor (subledger dist:ar).
        if (! Schema::hasTable('dist_ar_invoices')) {
            Schema::create('dist_ar_invoices', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('number', 40)->unique()->comment('AR/{ENT}/YYYY-NNNNN');
                $table->uuid('distributor_id');
                $table->string('source_type', 24)->default('sales')->comment('sales, retur, denda, manual');
                $table->string('source_ref', 80)->nullable();
                $table->bigInteger('amount_idr')->default(0);
                $table->bigInteger('paid_amount_idr')->default(0);
                $table->bigInteger('denda_idr')->default(0)->comment('Denda keterlambatan (simulasi)');
                $table->string('status', 16)->default('open')->comment('open, partial, paid, overdue, void');
                $table->date('invoice_date');
                $table->date('due_date');
                $table->text('notes')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->foreign('distributor_id')->references('id')->on('dist_distributors')->cascadeOnDelete();
                $table->index(['distributor_id', 'status', 'due_date']);
            });
        }

        if (! Schema::hasTable('dist_ar_payments')) {
            Schema::create('dist_ar_payments', function (Blueprint $table) {
                $table->id();
                $table->uuid('invoice_id');
                $table->bigInteger('amount_idr');
                $table->string('method', 24)->default('transfer');
                $table->string('reference', 80)->nullable();
                $table->date('paid_at');
                $table->timestamps();

                $table->foreign('invoice_id')->references('id')->on('dist_ar_invoices')->cascadeOnDelete();
                $table->index(['invoice_id']);
            });
        }

        // 42.5 Target penjualan & tier hak diskon.
        if (! Schema::hasTable('dist_targets')) {
            Schema::create('dist_targets', function (Blueprint $table) {
                $table->id();
                $table->uuid('distributor_id');
                $table->string('product_sku', 60)->comment('SKU prinsipal (store_products.sku / material code)');
                $table->string('period', 8)->comment('YYYY atau YYYY-Qn');
                $table->decimal('target_qty', 18, 6)->default(0);
                $table->decimal('achieved_qty', 18, 6)->default(0);
                $table->string('basis', 16)->default('sell_in')->comment('sell_in, sell_out');
                $table->timestamps();

                $table->foreign('distributor_id')->references('id')->on('dist_distributors')->cascadeOnDelete();
                $table->unique(['distributor_id', 'product_sku', 'period', 'basis']);
                $table->index(['period', 'basis']);
            });
        }

        if (! Schema::hasTable('dist_tiers')) {
            Schema::create('dist_tiers', function (Blueprint $table) {
                $table->id();
                $table->string('code', 16)->unique()->comment('bronze, silver, gold');
                $table->string('label', 60);
                $table->decimal('discount_percent', 8, 4)->default(0)->comment('Hak diskon harga tebus');
                $table->decimal('min_achievement_percent', 8, 4)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 42.7 Outlet/pelanggan distributor (sell-out).
        if (! Schema::hasTable('dist_outlets')) {
            Schema::create('dist_outlets', function (Blueprint $table) {
                $table->id();
                $table->uuid('distributor_id');
                $table->string('code', 40);
                $table->string('name', 180);
                $table->string('segment', 24)->default('retail')->comment('retail, horeca, modern, wholesale');
                $table->string('city', 60)->nullable();
                $table->string('address')->nullable();
                $table->unsignedBigInteger('territory_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('distributor_id')->references('id')->on('dist_distributors')->cascadeOnDelete();
                $table->foreign('territory_id')->references('id')->on('dist_territories')->nullOnDelete();
                $table->unique(['distributor_id', 'code']);
                $table->index(['segment', 'is_active']);
            });
        }

        // 42.8 Scorecard kinerja.
        if (! Schema::hasTable('dist_scorecards')) {
            Schema::create('dist_scorecards', function (Blueprint $table) {
                $table->id();
                $table->uuid('distributor_id');
                $table->string('period', 8)->comment('YYYY atau YYYY-Qn');
                $table->bigInteger('sell_in_idr')->default(0);
                $table->bigInteger('sell_out_idr')->default(0);
                $table->decimal('fill_rate_percent', 8, 4)->default(0);
                $table->decimal('dso_days', 8, 2)->default(0)->comment('Days sales outstanding');
                $table->decimal('price_compliance_percent', 8, 4)->default(100);
                $table->decimal('achievement_percent', 8, 4)->default(0);
                $table->decimal('score', 8, 4)->default(0)->comment('0–100');
                $table->string('recommended_tier', 8)->nullable();
                $table->timestamps();

                $table->foreign('distributor_id')->references('id')->on('dist_distributors')->cascadeOnDelete();
                $table->unique(['distributor_id', 'period']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dist_scorecards');
        Schema::dropIfExists('dist_outlets');
        Schema::dropIfExists('dist_tiers');
        Schema::dropIfExists('dist_targets');
        Schema::dropIfExists('dist_ar_payments');
        Schema::dropIfExists('dist_ar_invoices');
        Schema::dropIfExists('dist_securities');
        Schema::dropIfExists('dist_territory_coverage');
        Schema::dropIfExists('dist_territories');
        Schema::dropIfExists('dist_distributors');
    }
};
