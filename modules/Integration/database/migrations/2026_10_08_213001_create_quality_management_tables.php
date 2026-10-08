<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 213.2: Unified Nonconformance & Corrective and Preventive Action (CAPA) tracking
        Schema::create('ops_qms_capa_records', function (Blueprint $table) {
            $table->id();
            $table->string('capa_code')->unique();
            $table->string('domain_code');
            $table->string('standard_name'); // ISO_9001, HACCP, JCI, ISPS
            $table->text('nonconformance_description');
            $table->text('root_cause_analysis'); // 5-Why or Fishbone
            $table->text('corrective_action_plan');
            $table->timestamp('target_completion_date');
            $table->string('status')->default('OPEN'); // OPEN, VERIFIED_CLOSED, OVERDUE_ESCALATED
            $table->timestamps();
        });

        // 213.4: Customer / Partner Complaint Unified Intake with Financial Compensation Ledger Linkage
        Schema::create('ops_qms_complaints', function (Blueprint $table) {
            $table->id();
            $table->string('complaint_code')->unique();
            $table->string('channel'); // WEB, MOBILE, CALL_CENTER
            $table->string('affected_service_or_product');
            $table->decimal('compensation_amount_idr', 18, 2)->default(0.00);
            $table->string('ledger_credit_reference')->nullable();
            $table->string('resolution_status')->default('RESOLVED');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_qms_complaints');
        Schema::dropIfExists('ops_qms_capa_records');
    }
};
