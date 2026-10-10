<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Gapless Document Numbering Sequences
        Schema::create('core_document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('entity_code', 50)->default('DEFAULT')->index(); // company/business entity code
            $table->string('document_type', 50)->index(); // INVOICE, PO, CLAIM, SHIPMENT, CONTRACT, RECEIPT
            $table->unsignedSmallInteger('year')->index();
            $table->unsignedTinyInteger('month')->nullable()->index(); // null if reset yearly, 1-12 if reset monthly
            $table->string('prefix', 32)->default('');
            $table->string('suffix', 32)->default('');
            $table->unsignedBigInteger('current_number')->default(0);
            $table->unsignedTinyInteger('padding')->default(5); // e.g. 5 digits -> 00001
            $table->timestamps();

            $table->unique(
                ['entity_code', 'document_type', 'year', 'month'],
                'uq_doc_seq_entity_type_period'
            );
        });

        // 2. Centralized Document Store with Checksum, Retention, and Security Guard
        Schema::create('core_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('documentable_type', 160)->nullable();
            $table->string('documentable_id', 64)->nullable();
            $table->string('document_type', 50)->index(); // CONTRACT, INVOICE_PDF, RECEIPT_IMAGE, KYC_ID, ATTACHMENT
            $table->string('original_filename', 255);
            $table->string('stored_path', 500);
            $table->string('disk', 50)->default('local');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('file_size_bytes');
            $table->char('checksum_sha256', 64)->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->date('retention_until')->nullable()->index(); // Date when retention expires
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->index(['documentable_type', 'documentable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_documents');
        Schema::dropIfExists('core_document_sequences');
    }
};
