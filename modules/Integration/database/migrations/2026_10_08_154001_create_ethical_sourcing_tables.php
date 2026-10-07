<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 154.1: Global talent pool
        Schema::create('eth_global_candidates', function (Blueprint $table) {
            $table->id();
            $table->string('candidate_code')->unique();
            $table->string('name');
            $table->string('country_code', 2);
            $table->string('skill_domain');
            $table->boolean('work_auth_verified')->default(false);
            $table->string('matching_status')->default('AVAILABLE'); // AVAILABLE, MATCHED, HIRED
            $table->timestamps();
        });

        // 154.2: Ethical sourcing & supplier modern slavery audits
        Schema::create('eth_supplier_audits', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_code');
            $table->string('sector'); // MINING, PLANTATION, GARMENT, ELECTRONICS
            $table->decimal('ethical_score', 5, 2)->default(100.00); // 0 - 100
            $table->boolean('child_labor_detected')->default(false);
            $table->boolean('forced_labor_detected')->default(false);
            $table->string('status')->default('PASSED'); // PASSED, REMEDIATION, BLACKLISTED
            $table->text('remediation_plan')->nullable();
            $table->boolean('is_tender_eligible')->default(true);
            $table->timestamps();
        });

        // 154.3: Living wage benchmark per region
        Schema::create('eth_living_wage_benchmarks', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 2)->unique();
            $table->decimal('living_wage_monthly', 18, 2);
            $table->string('currency', 3);
            $table->timestamps();
        });

        // 154.4: Vendor code of conduct compliance
        Schema::create('eth_vendor_coc_records', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_code')->unique();
            $table->boolean('coc_signed')->default(false);
            $table->timestamp('signed_at')->nullable();
            $table->integer('breach_reports_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eth_vendor_coc_records');
        Schema::dropIfExists('eth_living_wage_benchmarks');
        Schema::dropIfExists('eth_supplier_audits');
        Schema::dropIfExists('eth_global_candidates');
    }
};
