<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datamesh_domain_products', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique();
            $table->string('domain_name')->index();
            $table->string('product_title');
            $table->integer('schema_version')->default(1);
            $table->decimal('quality_score', 5, 2);
            $table->boolean('meets_minimum_bar')->default(true); // 267.1
            $table->boolean('publishing_blocked')->default(false); // 267.5
            $table->string('publishing_status')->default('DRAFT'); // DRAFT, PUBLISHED, BLOCKED_POLICY_VIOLATION
            $table->timestamps();
        });

        Schema::create('datamesh_interoperability_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_code')->unique();
            $table->string('product_code')->index();
            $table->string('consumer_domain');
            $table->integer('target_sla_freshness_mins')->default(60);
            $table->integer('actual_freshness_mins')->default(30);
            $table->boolean('is_sla_breached')->default(false); // 267.4
            $table->decimal('compensation_credit_usd', 15, 2)->default(0.00); // 267.6
            $table->timestamps();
        });

        Schema::create('datamesh_product_usage_billing', function (Blueprint $table) {
            $table->id();
            $table->string('billing_code')->unique();
            $table->string('contract_code')->index();
            $table->integer('query_units_consumed');
            $table->decimal('rate_per_unit_usd', 10, 4)->default(0.0500);
            $table->decimal('gross_amount_usd', 15, 2);
            $table->decimal('net_billed_amount_usd', 15, 2); // 267.4
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datamesh_product_usage_billing');
        Schema::dropIfExists('datamesh_interoperability_contracts');
        Schema::dropIfExists('datamesh_domain_products');
    }
};
