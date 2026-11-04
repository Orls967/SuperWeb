<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // D83-1 Escrow USDT per L/C (micro-unit integer, 6 desimal).
        Schema::create('tf_clearing_escrows', function (Blueprint $table) {
            $table->id();
            $table->string('escrow_code', 32)->unique();
            $table->uuid('letter_of_credit_id');
            $table->unsignedBigInteger('importer_user_id');
            $table->unsignedBigInteger('exporter_user_id');
            $table->unsignedBigInteger('usdt_micro');
            $table->unsignedBigInteger('deposit_fx_rate_idr');
            $table->string('status', 16)->default('DEPOSITED'); // DEPOSITED, RELEASED
            $table->string('deposit_idempotency_key', 64)->unique();
            $table->timestamp('deposited_at');
            // Rilis: hanya setelah K6 memverifikasi hash BL/POD dan rantai custody valid.
            $table->string('release_document_type', 4)->nullable();
            $table->string('release_document_hash', 64)->nullable();
            $table->string('custody_shipment_ref', 32)->nullable(); // rujukan lintas modul (Logistics), tanpa FK
            $table->string('custody_event_ref', 32)->nullable();
            $table->unsignedBigInteger('release_fx_rate_idr')->nullable();
            $table->bigInteger('fx_revaluation_idr')->nullable(); // bertanda: + untung, - rugi
            $table->unsignedBigInteger('released_by_user_id')->nullable();
            $table->string('release_idempotency_key', 64)->nullable()->unique();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->unique('letter_of_credit_id');
            $table->unique(['letter_of_credit_id', 'release_document_hash'], 'uq_tf_clr_lc_doc');
            $table->index('importer_user_id');
            $table->index('exporter_user_id');

            $table->foreign('letter_of_credit_id')->references('id')->on('tf_letters_of_credit');
            $table->foreign('importer_user_id')->references('id')->on('users');
            $table->foreign('exporter_user_id')->references('id')->on('users');
            $table->foreign('released_by_user_id')->references('id')->on('users');
        });

        // D83-3 Parameter CBAM berversi (S2 — wajib dikonfirmasi pemilik sebelum produksi, U-7).
        Schema::create('tf_cbam_parameters', function (Blueprint $table) {
            $table->id();
            $table->string('version', 16)->unique();
            $table->unsignedBigInteger('price_eur_cents_per_tco2e');
            $table->unsignedBigInteger('eur_to_idr_rate');
            $table->string('label', 4)->default('S2');
            $table->string('source_label', 96);
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->timestamps();
        });

        // D83-3 Sertifikat CBAM per kontainer ekspor ke UE: immutable, bernomor gapless, berhash.
        Schema::create('tf_cbam_declarations', function (Blueprint $table) {
            $table->id();
            $table->string('certificate_number', 40)->unique();
            $table->string('container_number', 32)->unique();
            $table->string('product_code', 32);
            $table->string('origin_country', 2);
            $table->unsignedBigInteger('net_mass_kg');
            $table->decimal('specific_t_co2e_per_t', 12, 6);
            $table->string('verification_ref', 64);
            $table->unsignedBigInteger('embedded_milli_tco2e'); // tCO2e × 1.000, HalfUp 3 desimal
            $table->string('parameter_version', 16);
            $table->unsignedBigInteger('levy_idr');
            $table->string('simulation_label', 4)->default('S2');
            $table->string('document_checksum', 64);
            $table->string('certificate_hash', 64);
            $table->string('idempotency_key', 64)->unique();
            $table->unsignedBigInteger('declared_by_user_id')->nullable();
            $table->timestamp('declared_at');
            $table->timestamps();

            $table->foreign('declared_by_user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tf_cbam_declarations');
        Schema::dropIfExists('tf_cbam_parameters');
        Schema::dropIfExists('tf_clearing_escrows');
    }
};
