<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hcm_departments')) {
            Schema::create('hcm_departments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('code', 40)->unique();
                $table->string('name', 120);
                $table->uuid('parent_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hcm_employees')) {
            Schema::create('hcm_employees', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('employee_number', 40)->unique();
                $table->string('name', 160);
                $table->string('nik_hash', 64)->nullable();
                $table->string('email', 120)->nullable();
                $table->uuid('department_id')->nullable();
                $table->string('position', 100);
                $table->string('employment_type', 30)->default('PKWTT'); // PKWT, PKWTT, casual
                $table->bigInteger('basic_salary_idr')->default(0);
                $table->bigInteger('allowances_idr')->default(0);
                $table->string('bank_name', 60)->default('Bank Mandiri');
                $table->string('bank_account_number', 60)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hcm_payrolls')) {
            Schema::create('hcm_payrolls', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('employee_id');
                $table->string('period', 7); // YYYY-MM
                $table->bigInteger('gross_salary_idr');
                $table->bigInteger('deductions_idr')->default(0);
                $table->bigInteger('net_salary_idr');
                $table->bigInteger('pph21_idr')->default(0);
                $table->bigInteger('bpjs_tk_idr')->default(0);
                $table->bigInteger('bpjs_kes_idr')->default(0);
                $table->string('status', 30)->default('draft'); // draft, approved, paid
                $table->timestamps();

                $table->unique(['employee_id', 'period']);
            });
        }

        if (! Schema::hasTable('hcm_production_labor_allocations')) {
            Schema::create('hcm_production_labor_allocations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('payroll_id');
                $table->string('work_order_ref', 60);
                $table->decimal('hours_worked', 8, 2)->default(0);
                $table->bigInteger('allocated_cost_idr')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_production_labor_allocations');
        Schema::dropIfExists('hcm_payrolls');
        Schema::dropIfExists('hcm_employees');
        Schema::dropIfExists('hcm_departments');
    }
};
