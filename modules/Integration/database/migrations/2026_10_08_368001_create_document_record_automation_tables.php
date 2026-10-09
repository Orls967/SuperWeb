<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_document_extractions', function (Blueprint $table) {
            $table->id();
            $table->string('document_code')->unique();
            $table->string('document_type'); // FINANCIAL_INVOICE, CLAIM_FORM
            $table->decimal('extraction_confidence', 5, 4);
            $table->decimal('min_confidence_threshold', 5, 4)->default(0.9500);
            $table->boolean('human_verified')->default(false); // 368.2 & 368.5 Edge case
            $table->boolean('auto_posting_permitted')->default(false); // 368.4
            $table->timestamps();
        });

        Schema::create('platform_document_legal_holds', function (Blueprint $table) {
            $table->id();
            $table->string('hold_code')->unique();
            $table->string('document_code')->index();
            $table->boolean('is_legal_hold_active')->default(true); // 368.3, 368.4, 368.6 Risk
            $table->boolean('deletion_prevented')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_document_legal_holds');
        Schema::dropIfExists('platform_document_extractions');
    }
};
