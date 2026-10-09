<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 44.1–44.7 Pricing engine: price list, diskon, promo/klaim, kunci harga,
// margin minimum & override, waterfall, analitik.
return new class extends Migration
{
    public function up(): void
    {
        // 44.1 Price list per segmen/saluran/wilayah/mata uang, tanpa overlap.
        Schema::create('pric_price_lists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name', 160);
            $table->string('channel', 24)->default('general')->comment('general, retail, horeca, modern, export');
            $table->string('segment', 24)->nullable()->comment('distributor, agent, end_customer');
            $table->string('region_code', 40)->nullable()->comment('Wilayah/teritori');
            $table->string('currency', 3)->default('IDR');
            $table->unsignedSmallInteger('priority')->default(100)->comment('Rendah = menang');
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->string('status', 16)->default('draft')->comment('draft, active, retired');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['channel', 'status', 'valid_from', 'valid_until']);
            $table->index(['segment', 'region_code']);
        });

        Schema::create('pric_price_list_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('price_list_id');
            $table->string('sku', 60);
            $table->bigInteger('price_idr')->comment('Harga daftar (satuan dasar)');
            $table->decimal('min_qty', 18, 6)->default(1)->comment('Tier qty opsional');
            $table->timestamps();

            $table->foreign('price_list_id')->references('id')->on('pric_price_lists')->cascadeOnDelete();
            $table->index(['sku', 'price_list_id']);
            $table->unique(['price_list_id', 'sku', 'min_qty']);
        });

        // 44.2 Diskon bertingkat: volume, bundle, kombinasi, kupon.
        Schema::create('pric_discount_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name', 160);
            $table->string('kind', 16)->comment('volume, bundle, combo, coupon');
            $table->string('channel', 24)->default('general');
            $table->string('region_code', 40)->nullable();
            $table->decimal('threshold_qty', 18, 6)->default(0)->comment('Ambang volume');
            $table->bigInteger('threshold_amount_idr')->default(0)->comment('Ambang nilai');
            $table->decimal('percent_off', 8, 4)->default(0);
            $table->bigInteger('amount_off_idr')->default(0);
            $table->unsignedSmallInteger('order')->default(10)->comment('Urutan penerapan (rendah dulu)');
            $table->json('applies_to')->nullable()->comment('SKU/qty bundle; null = semua');
            $table->string('coupon_code', 60)->nullable()->unique()->comment('Untuk kind=coupon');
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->boolean('stackable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['kind', 'is_active', 'valid_from']);
        });

        // 44.3 Promo dagang: anggaran, klaim distributor + bukti.
        Schema::create('pric_promotions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name', 160);
            $table->string('mechanic', 24)->comment('off_invoice, bill_back, scan_back');
            $table->bigInteger('budget_idr')->default(0);
            $table->bigInteger('spent_idr')->default(0);
            $table->decimal('percent_off', 8, 4)->default(0);
            $table->bigInteger('amount_off_idr')->default(0);
            $table->json('applies_to')->nullable()->comment('SKU/segmen sasaran');
            $table->date('valid_from');
            $table->date('valid_until');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'valid_from', 'valid_until']);
        });

        Schema::create('pric_promotion_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('promotion_id');
            $table->uuid('distributor_id')->nullable()->comment('Tautan dist_ (query mentah)');
            $table->string('source_ref', 80)->comment('Nomor order/invoice pembanding');
            $table->bigInteger('amount_idr');
            $table->string('status', 16)->default('submitted')->comment('submitted, validated, approved, rejected, settled');
            $table->text('evidence_note')->nullable();
            $table->string('evidence_document', 255)->nullable()->comment('Referensi dokumen (DocumentStore)');
            $table->unsignedBigInteger('approval_id')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('promotion_id')->references('id')->on('pric_promotions')->cascadeOnDelete();
            $table->index(['status', 'promotion_id']);
        });

        // 44.4 Kunci harga di dokumen (immutable) — snapshot saat order.
        Schema::create('pric_price_locks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('subject_type', 32)->comment('store_order, dist_order, contract');
            $table->string('subject_id', 36);
            $table->string('sku', 60);
            $table->decimal('qty', 18, 6)->default(1);
            $table->bigInteger('list_price_idr')->default(0);
            $table->bigInteger('applied_price_idr')->default(0);
            $table->bigInteger('discount_idr')->default(0);
            $table->uuid('price_list_id')->nullable();
            $table->uuid('source_rule_id')->nullable()->comment('Diskon/promo/kontrak pemenang');
            $table->string('source_kind', 16)->default('price_list')->comment('price_list, contract, discount, promo, override');
            $table->json('waterfall')->nullable()->comment('Riwayat penerapan deterministik');
            $table->string('reason', 300)->nullable()->comment('Alasan override');
            $table->timestamp('locked_at');
            $table->timestamps();

            $table->foreign('price_list_id')->references('id')->on('pric_price_lists')->nullOnDelete();
            $table->index(['subject_type', 'subject_id']);
            $table->unique(['subject_type', 'subject_id', 'sku']);
        });

        // 44.5 Margin minimum & approval override harga.
        Schema::create('pric_margin_policies', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 24)->default('general');
            $table->string('sku', 60)->nullable()->comment('null = channel-wide');
            $table->decimal('min_margin_percent', 8, 4)->default(0);
            $table->bigInteger('floor_cost_idr')->default(0)->comment('Biaya dasar / floor harga');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['channel', 'sku']);
        });

        Schema::create('pric_price_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sku', 60);
            $table->string('channel', 24)->default('general');
            $table->bigInteger('proposed_price_idr');
            $table->bigInteger('floor_price_idr')->default(0);
            $table->decimal('margin_percent', 8, 4)->default(0);
            $table->string('status', 16)->default('pending')->comment('pending, approved, rejected');
            $table->unsignedBigInteger('approval_id')->nullable();
            $table->string('reason', 300);
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'sku']);
        });

        // 44.6 Event perubahan harga idempoten (outbox snapshot).
        Schema::create('pric_price_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 64)->unique()->comment('Idempoten per pembaruan');
            $table->string('kind', 24)->comment('price_list_activated, discount_changed, override_applied');
            $table->string('subject_type', 32);
            $table->string('subject_id', 36);
            $table->json('payload')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['kind', 'recorded_at']);
        });

        // 44.7 Analitik: realisasi vs list, kebocoran diskon, efektivitas promo.
        Schema::create('pric_analytics_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('period', 8)->comment('YYYY-MM');
            $table->string('channel', 24)->default('general');
            $table->decimal('realized_vs_list_percent', 8, 4)->default(0)->comment('Rasio harga jual/list');
            $table->decimal('discount_leakage_idr', 20, 2)->default(0)->comment('Diskon di luar aturan');
            $table->decimal('promo_effectiveness_percent', 8, 4)->default(0);
            $table->decimal('avg_discount_percent', 8, 4)->default(0);
            $table->bigInteger('volume_idr')->default(0);
            $table->json('breakdown')->nullable();
            $table->timestamps();

            $table->unique(['period', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pric_analytics_snapshots');
        Schema::dropIfExists('pric_price_events');
        Schema::dropIfExists('pric_price_overrides');
        Schema::dropIfExists('pric_margin_policies');
        Schema::dropIfExists('pric_price_locks');
        Schema::dropIfExists('pric_promotion_claims');
        Schema::dropIfExists('pric_promotions');
        Schema::dropIfExists('pric_discount_rules');
        Schema::dropIfExists('pric_price_list_items');
        Schema::dropIfExists('pric_price_lists');
    }
};
