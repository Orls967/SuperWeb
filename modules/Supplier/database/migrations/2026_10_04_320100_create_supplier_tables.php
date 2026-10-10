<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 32.1 Profil pemasok/produsen (menautkan party role 'supplier')
        Schema::create('sup_suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('party_id')->nullable()->comment('Tautan ke pty_parties (Fase 27)');
            $table->string('code', 40)->unique()->comment('Kode via DocumentNumbering (26.8): SUP/{ENT}/YYYY-NNNNN');
            $table->string('name', 200);
            $table->string('kind', 32)->default('supplier')->comment('producer, supplier, distributor, agent');
            $table->string('status', 32)->default('candidate')->comment('candidate, approved, preferred, probation, disqualified');
            $table->unsignedSmallInteger('lead_time_days')->default(7);
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->unsignedTinyInteger('rating')->default(5)->comment('1-5 bintang ringkas');
            $table->text('capabilities')->nullable()->comment('JSON: kategori barang/jasa yang ditangani');
            $table->text('factory_locations')->nullable()->comment('JSON: lokasi pabrik/gudang');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('party_id')->references('id')->on('pty_parties')->nullOnDelete();
            $table->index(['status', 'is_active']);
            $table->index('kind');
        });

        // 32.1 Sertifikasi ber masa berlaku (ISO, SNI, Halal, BPOM, GMP)
        Schema::create('sup_certifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('supplier_id');
            $table->string('type', 32)->comment('iso9001, iso22000, sni, halal, bpom, gmp, haccp, other');
            $table->string('number', 100)->nullable();
            $table->string('issuer', 160)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('document_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('sup_suppliers')->cascadeOnDelete();
            $table->index(['type', 'expires_at']);
        });

        // 32.2 Kualifikasi & onboarding: kuesioner + audit lokasi + approval
        Schema::create('sup_qualifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('supplier_id');
            $table->string('type', 32)->default('questionnaire')->comment('questionnaire, site_audit');
            $table->json('answers')->nullable()->comment('Kuesioner / checklist jawaban');
            $table->json('scores')->nullable()->comment('Skor per butir');
            $table->unsignedTinyInteger('total_score')->default(0)->comment('0-100');
            $table->string('result', 32)->default('pending')->comment('pending, pass, fail');
            $table->foreignId('approval_id')->nullable()->constrained('core_approvals')->nullOnDelete();
            $table->string('approval_status', 16)->default('pending')->comment('pending, approved, rejected');
            $table->foreignId('assessed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('sup_suppliers')->cascadeOnDelete();
            $table->index(['approval_status', 'result']);
        });

        // 32.2 Jejak transisi status onboarding
        Schema::create('sup_status_histories', function (Blueprint $table) {
            $table->id();
            $table->uuid('supplier_id');
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('reason')->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('sup_suppliers')->cascadeOnDelete();
            $table->index(['supplier_id', 'created_at']);
        });

        // 32.3 Katalog & harga pemasok: SKU pemasok ↔ SKU internal, harga bertingkat
        Schema::create('sup_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('supplier_id');
            $table->string('supplier_sku', 80);
            $table->unsignedBigInteger('internal_product_id')->nullable()->comment('store_products.id atau ingridient — tautan opsional');
            $table->string('internal_sku', 80)->nullable();
            $table->string('name', 200);
            $table->string('unit', 32)->default('pcs');
            $table->unsignedInteger('moq')->default(1);
            $table->unsignedInteger('lead_time_days')->default(7);
            $table->string('currency', 3)->default('IDR');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('sup_suppliers')->cascadeOnDelete();
            $table->unique(['supplier_id', 'supplier_sku']);
            $table->index(['internal_product_id']);
        });

        // 32.3 Harga bertingkat per item (qty minimum), tanpa overlap periode
        Schema::create('sup_price_tiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('min_qty')->default(1);
            $table->unsignedBigInteger('max_qty')->nullable();
            $table->decimal('unit_price', 20, 4)->comment('Minor unit / decimal 4 (multi-currency Fase 48)');
            $table->string('currency', 3)->default('IDR');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('item_id')->references('id')->on('sup_items')->cascadeOnDelete();
            $table->index(['item_id', 'valid_from', 'valid_to']);
        });

        // 32.6 Skor pemasok periodik (OTD, reject, harga, respons)
        Schema::create('sup_scorecards', function (Blueprint $table) {
            $table->id();
            $table->uuid('supplier_id');
            $table->string('period', 7)->comment('YYYY-MM');
            $table->unsignedTinyInteger('otd_score')->default(100)->comment('On-Time Delivery 0-100');
            $table->unsignedTinyInteger('quality_score')->default(100)->comment('Reject rate inverted 0-100');
            $table->unsignedTinyInteger('price_score')->default(100)->comment('Harga vs pasar 0-100 (simulasi)');
            $table->unsignedTinyInteger('responsiveness_score')->default(100);
            $table->unsignedTinyInteger('overall_score')->default(100);
            $table->string('action', 32)->default('none')->comment('none, corrective, scar, probation, review');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('sup_suppliers')->cascadeOnDelete();
            $table->unique(['supplier_id', 'period']);
        });

        // 32.7 Manajemen risiko: konsentrasi, ketergantungan, sertifikat kedaluwarsa
        Schema::create('sup_risk_flags', function (Blueprint $table) {
            $table->id();
            $table->uuid('supplier_id');
            $table->string('type', 32)->comment('single_source, expiring_cert, sanctions, score_below_threshold');
            $table->string('severity', 16)->default('medium')->comment('low, medium, high, critical');
            $table->text('message');
            $table->json('meta')->nullable();
            $table->boolean('is_open')->default(true);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('sup_suppliers')->cascadeOnDelete();
            $table->index(['is_open', 'severity']);
        });

        // 32.5 Portal pemasok: konfirmasi PO & ASN (advance ship notice)
        Schema::create('sup_asns', function (Blueprint $table) {
            $table->id();
            $table->uuid('supplier_id');
            $table->string('asn_number', 40)->unique()->comment('Nomor via 26.8');
            $table->unsignedBigInteger('purchase_order_id')->nullable()->comment('resto_purchase_orders.id — tautan lintas modul');
            $table->string('status', 24)->default('draft')->comment('draft, shipped, received, cancelled');
            $table->date('ship_date')->nullable();
            $table->date('expected_arrival')->nullable();
            $table->json('lines')->comment('Snapshot: sku, qty, batch, coa_ref');
            $table->string('tracking_ref')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('sup_suppliers')->cascadeOnDelete();
            $table->index(['status', 'expected_arrival']);
        });

        // 32.5 Sertifikat/COA diunggah portal pemasok (via Core DocumentStore id)
        Schema::create('sup_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('supplier_id');
            $table->unsignedBigInteger('document_id')->comment('core_documents.id (DocumentStore)');
            $table->string('kind', 32)->default('coa')->comment('coa, certificate, spec, invoice, other');
            $table->string('label');
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('sup_suppliers')->cascadeOnDelete();
            $table->index(['supplier_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sup_documents');
        Schema::dropIfExists('sup_asns');
        Schema::dropIfExists('sup_risk_flags');
        Schema::dropIfExists('sup_scorecards');
        Schema::dropIfExists('sup_price_tiers');
        Schema::dropIfExists('sup_items');
        Schema::dropIfExists('sup_status_histories');
        Schema::dropIfExists('sup_qualifications');
        Schema::dropIfExists('sup_certifications');
        Schema::dropIfExists('sup_suppliers');
    }
};
