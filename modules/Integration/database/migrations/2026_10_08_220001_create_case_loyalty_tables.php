<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 220.2 & 220.6: Cross-line parent case management with orchestrator subcases
        Schema::create('crm_cross_line_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_code')->unique();
            $table->string('customer_golden_id');
            $table->text('issue_summary');
            $table->string('status')->default('IN_PROGRESS'); // IN_PROGRESS, RESOLVED_CLOSED
            $table->timestamps();
        });

        Schema::create('crm_cross_line_subcases', function (Blueprint $table) {
            $table->id();
            $table->string('subcase_code')->unique();
            $table->string('case_code');
            $table->string('business_line'); // HOSPITAL, HOTEL, LOGISTICS
            $table->string('status')->default('OPEN'); // OPEN, RESOLVED
            $table->timestamps();
        });

        // 220.3: Unified loyalty points ledger across 30 lines with conservation
        Schema::create('crm_loyalty_point_ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('tx_code')->unique();
            $table->string('customer_golden_id');
            $table->string('business_line');
            $table->integer('earned_points')->default(0);
            $table->integer('redeemed_points')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_loyalty_point_ledgers');
        Schema::dropIfExists('crm_cross_line_subcases');
        Schema::dropIfExists('crm_cross_line_cases');
    }
};
