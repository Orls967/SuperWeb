<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 216.1: Field Service Dispatch with Mandatory Certification & Skill Gating
        Schema::create('ops_fs_technicians', function (Blueprint $table) {
            $table->id();
            $table->string('technician_code')->unique();
            $table->string('technician_name');
            $table->string('service_domain'); // MEDICAL_EQUIPMENT, TELECOM_NETWORK, AUTOSERVE
            $table->json('certifications'); // Array of active certification codes
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ops_fs_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('dispatch_code')->unique();
            $table->string('technician_code');
            $table->string('required_certification');
            $table->string('service_order_code');
            $table->string('status')->default('DISPATCHED');
            $table->timestamps();
        });

        // 216.2: Centralized SLA Engine with Automated Breach Detection & Penalty Credits
        Schema::create('ops_fs_sla_records', function (Blueprint $table) {
            $table->id();
            $table->string('sla_record_code')->unique();
            $table->string('service_order_code');
            $table->integer('sla_target_minutes');
            $table->integer('actual_resolution_minutes');
            $table->boolean('is_breached')->default(false);
            $table->decimal('penalty_credit_amount_idr', 18, 2)->default(0.00);
            $table->string('penalty_status')->default('NONE'); // NONE, PENALTY_CREDITED
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_fs_sla_records');
        Schema::dropIfExists('ops_fs_dispatches');
        Schema::dropIfExists('ops_fs_technicians');
    }
};
