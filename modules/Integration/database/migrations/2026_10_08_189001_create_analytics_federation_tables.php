<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 189.1: Data product catalog across 30 lines
        Schema::create('anl_data_products', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique();
            $table->string('domain_code'); // L01 - L30
            $table->string('product_name');
            $table->integer('sla_freshness_minutes')->default(60);
            $table->timestamp('last_refreshed_at');
            $table->boolean('sla_breached')->default(false);
            $table->timestamps();
        });

        // 189.2: Federated KPI metric store with voucher ledger lineage
        Schema::create('anl_federated_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('metric_code')->unique(); // GMV, ADR, OCCUPANCY, LOSS_RATIO
            $table->string('owning_domain');
            $table->decimal('metric_value', 18, 2);
            $table->string('ledger_voucher_reference')->nullable();
            $table->timestamps();
        });

        // 189.3: Privacy-preserving export requests with k-anonymity checks
        Schema::create('anl_cross_line_exports', function (Blueprint $table) {
            $table->id();
            $table->string('export_code')->unique();
            $table->string('requesting_domain');
            $table->integer('cohort_size');
            $table->integer('min_k_anonymity_threshold')->default(10);
            $table->boolean('privacy_check_passed')->default(false);
            $table->string('status')->default('PENDING'); // APPROVED, REJECTED
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anl_cross_line_exports');
        Schema::dropIfExists('anl_federated_metrics');
        Schema::dropIfExists('anl_data_products');
    }
};
