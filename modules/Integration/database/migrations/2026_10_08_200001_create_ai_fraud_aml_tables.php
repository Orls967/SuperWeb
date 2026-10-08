<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 200.1 & 200.2: Cross-line Fraud & AML Case Management Mesh
        Schema::create('ai_fraud_mesh_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_code')->unique();
            $table->string('entity_id');
            $table->json('contributing_line_signals'); // Signals aggregated across lines
            $table->decimal('composite_fraud_score', 5, 2); // 0.00 to 100.00
            $table->string('case_status')->default('OPEN'); // OPEN, INVESTIGATING, FROZEN, CLOSED
            $table->string('freeze_approved_by')->nullable();
            $table->timestamps();
        });

        // 200.3: Gapless Suspicious Activity Report (SAR) numbering
        Schema::create('ai_aml_sar_filings', function (Blueprint $table) {
            $table->id();
            $table->integer('sar_sequence_number')->unique();
            $table->string('sar_reference_code')->unique();
            $table->string('case_code');
            $table->decimal('suspicious_amount_idr', 18, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_aml_sar_filings');
        Schema::dropIfExists('ai_fraud_mesh_cases');
    }
};
