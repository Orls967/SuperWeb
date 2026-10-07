<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 163.1: BMT and microfinance group financing (tanggung renteng)
        Schema::create('bmt_group_financings', function (Blueprint $table) {
            $table->id();
            $table->string('group_code')->unique();
            $table->string('group_name');
            $table->integer('total_members')->default(5);
            $table->decimal('total_facility_amount', 18, 2);
            $table->decimal('outstanding_balance', 18, 2);
            $table->boolean('group_liability_active')->default(true); // Tanggung renteng
            $table->timestamps();
        });

        // 163.2: Gig worker microcredit with waterfall auto-deduct
        Schema::create('bmt_gig_microcredits', function (Blueprint $table) {
            $table->id();
            $table->string('loan_code')->unique();
            $table->unsignedBigInteger('worker_id');
            $table->decimal('principal_amount', 18, 2);
            $table->decimal('remaining_amount', 18, 2);
            $table->decimal('waterfall_deduct_pct', 5, 2)->default(20.00); // 20% auto-deduct from payout
            $table->string('status')->default('ACTIVE'); // ACTIVE, PAID_OFF
            $table->timestamps();
        });

        // 163.3: Farmer milestone disbursement guarded by NDVI
        Schema::create('bmt_farmer_milestones', function (Blueprint $table) {
            $table->id();
            $table->string('farmer_id');
            $table->string('milestone_name'); // SEEDING, VEGETATIVE, HARVEST
            $table->decimal('disbursement_amount', 18, 2);
            $table->decimal('required_min_ndvi', 5, 3)->default(0.300);
            $table->decimal('current_ndvi', 5, 3)->nullable();
            $table->boolean('disbursed')->default(false);
            $table->timestamps();
        });

        // 163.5: Social impact metrics
        Schema::create('bmt_social_impact_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('period_year', 4);
            $table->integer('beneficiaries_count')->default(0);
            $table->integer('jobs_created_count')->default(0);
            $table->integer('msme_graduated_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bmt_social_impact_metrics');
        Schema::dropIfExists('bmt_farmer_milestones');
        Schema::dropIfExists('bmt_gig_microcredits');
        Schema::dropIfExists('bmt_group_financings');
    }
};
