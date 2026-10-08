<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('multimodal_financial_document_extractions', function (Blueprint $table) {
            $table->id();
            $table->string('extraction_code')->unique();
            $table->string('document_type'); // INVOICE, BOL, LAB_REPORT
            $table->string('source_document_url');
            $table->decimal('extracted_amount_usd', 18, 2);
            $table->boolean('human_verified_material_fields')->default(false); // 351.1 & 351.4
            $table->boolean('posted_to_financial_ledger')->default(false); // 351.5 Edge case
            $table->timestamps();
        });

        Schema::create('multimodal_vision_inspection_events', function (Blueprint $table) {
            $table->id();
            $table->string('inspection_code')->unique();
            $table->string('inspection_type'); // PPE_COMPLIANCE, QC_VISUAL_DEFECT
            $table->decimal('confidence_score', 5, 4);
            $table->decimal('confidence_threshold', 5, 4)->default(0.8500);
            $table->boolean('human_confirmation_obtained')->default(false); // 351.2 & 351.4
            $table->boolean('consequential_action_taken')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('multimodal_vision_inspection_events');
        Schema::dropIfExists('multimodal_financial_document_extractions');
    }
};
