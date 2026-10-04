<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 7. Lampiran & dokumen pendukung kontrak (28.6)
        // Berkas sendiri disimpan oleh Core DocumentStore (checksum + retensi);
        // tabel ini hanya menautkan dokumen ke kontrak / pihak / entitas hukum.
        Schema::create('ctr_contract_attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('contract_id');
            $table->unsignedBigInteger('document_id')->comment('FK ke core_documents (DocumentStore)');
            $table->uuid('contract_party_id')->nullable()->comment('Tautan ke penandatangan bila dokumen terkait tanda tangan');
            $table->uuid('legal_entity_id')->nullable()->comment('Tautan ke entitas hukum pemegang kontrak');
            $table->string('label')->comment('Nama tampilan lampiran');
            $table->string('kind')->default('supporting')->comment('signed_copy, annex, supporting, kyc');
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('ctr_contracts')->cascadeOnDelete();
            $table->foreign('contract_party_id')->references('id')->on('ctr_contract_parties')->nullOnDelete();
            $table->foreign('legal_entity_id')->references('id')->on('pty_legal_entities')->nullOnDelete();
            $table->index(['contract_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ctr_contract_attachments');
    }
};
