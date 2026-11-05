<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syb_trade_custody_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 32)->index();
            $table->string('document_number', 64)->unique();
            $table->string('issuer_name', 128);
            $table->string('carrier_name', 128);
            $table->text('cargo_description');
            $table->string('document_hash_sha256', 64);
            $table->timestamp('verified_qabdh_at')->nullable()->index();
            $table->string('verifier_officer_ref', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('syb_trade_custody_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custody_document_id')->constrained('syb_trade_custody_documents')->cascadeOnDelete();
            $table->string('inspector_party_ref', 64)->index();
            $table->string('physical_location', 128);
            $table->string('seal_number', 64)->nullable();
            $table->string('condition_grade', 32);
            $table->text('inspection_notes')->nullable();
            $table->timestamp('inspected_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_trade_custody_inspections');
        Schema::dropIfExists('syb_trade_custody_documents');
    }
};
