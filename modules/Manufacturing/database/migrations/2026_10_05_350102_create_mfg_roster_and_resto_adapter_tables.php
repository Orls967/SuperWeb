<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 35.7 Adapter CK-01 → plant central_kitchen; no duplicate Resto domain data.
        Schema::create('mfg_resto_adapters', function (Blueprint $table) {
            $table->id();
            $table->uuid('plant_id');
            $table->unsignedBigInteger('outlet_id')->nullable()->comment('resto_outlets.id');
            $table->string('adapter_type', 24)->default('central_kitchen');
            $table->string('status', 16)->default('active');
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_key', 80)->nullable();
            $table->timestamps();

            $table->foreign('plant_id')->references('id')->on('mfg_plants')->cascadeOnDelete();
            $table->unique(['plant_id', 'outlet_id']);
        });

        // 35.8 Tenaga kerja produksi: operator, skill/sertifikasi, jadwal shift (no payroll).
        Schema::create('mfg_workers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_code', 40)->unique();
            $table->string('name', 160);
            $table->string('status', 16)->default('active');
            $table->json('skills')->nullable();
            $table->json('certifications')->nullable()->comment('nama, issued_at, expires_at');
            $table->timestamps();
        });

        Schema::create('mfg_worker_shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('worker_id');
            $table->unsignedBigInteger('shift_id');
            $table->date('work_date');
            $table->string('status', 16)->default('assigned')->comment('assigned, attended, absent, overtime');
            $table->unsignedSmallInteger('overtime_minutes')->default(0);
            $table->timestamps();

            $table->foreign('worker_id')->references('id')->on('mfg_workers')->cascadeOnDelete();
            $table->foreign('shift_id')->references('id')->on('mfg_shifts')->cascadeOnDelete();
            $table->unique(['worker_id', 'shift_id', 'work_date']);
            $table->index(['work_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_worker_shifts');
        Schema::dropIfExists('mfg_workers');
        Schema::dropIfExists('mfg_resto_adapters');
    }
};
