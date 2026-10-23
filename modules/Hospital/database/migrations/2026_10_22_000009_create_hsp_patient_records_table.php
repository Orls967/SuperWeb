<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hsp_patient_records')) {
            Schema::create('hsp_patient_records', function (Blueprint $table): void {
                $table->id();
                $table->string('patient_mrn', 32)->unique();
                $table->text('national_id_nik'); // AES-256 encrypted payload
                $table->string('full_name', 128);
                $table->text('icd10_diagnosis_code'); // AES-256 encrypted payload
                $table->text('prescription_details'); // AES-256 encrypted payload
                $table->foreignId('treating_doctor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hsp_patient_records');
    }
};
