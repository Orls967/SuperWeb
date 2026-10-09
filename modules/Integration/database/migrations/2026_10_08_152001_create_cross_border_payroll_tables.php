<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 152.1: Country payroll rule table
        Schema::create('gbl_country_payroll_rules', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 2)->unique();
            $table->string('currency', 3);
            $table->decimal('income_tax_rate_pct', 5, 2)->default(20.00);
            $table->decimal('social_security_rate_pct', 5, 2)->default(5.00);
            $table->boolean('has_13th_month')->default(false);
            $table->timestamps();
        });

        // 152.2: Assignment contracts for expatriates
        Schema::create('gbl_expatriate_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('assignment_code')->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('home_country', 2)->default('ID');
            $table->string('host_country', 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('base_salary', 18, 2);
            $table->string('currency', 3);
            $table->string('status')->default('ACTIVE'); // ACTIVE, COMPLETED, BLOCKED
            $table->timestamps();
        });

        // 152.3: Immigration permits
        Schema::create('gbl_work_permits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('country_code', 2);
            $table->string('permit_number')->unique();
            $table->date('valid_until');
            $table->string('status')->default('ACTIVE'); // ACTIVE, EXPIRED, REVOKED
            $table->timestamps();
        });

        // 152.4: Cross-border pay runs & shadow payroll records
        Schema::create('gbl_pay_run_records', function (Blueprint $table) {
            $table->id();
            $table->string('run_code');
            $table->string('assignment_code')->nullable();
            $table->string('country_code', 2);
            $table->decimal('gross_pay', 18, 2);
            $table->decimal('tax_amount', 18, 2);
            $table->decimal('social_security_amount', 18, 2);
            $table->decimal('net_pay', 18, 2);
            $table->decimal('shadow_payroll_tax', 18, 2)->default(0.00);
            $table->boolean('is_shadow')->default(false);
            $table->string('currency', 3);
            $table->decimal('consolidated_cost_idr', 18, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gbl_pay_run_records');
        Schema::dropIfExists('gbl_work_permits');
        Schema::dropIfExists('gbl_expatriate_assignments');
        Schema::dropIfExists('gbl_country_payroll_rules');
    }
};
