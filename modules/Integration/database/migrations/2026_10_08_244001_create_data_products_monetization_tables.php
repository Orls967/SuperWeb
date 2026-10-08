<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_product_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique();
            $table->string('product_name');
            $table->decimal('subscription_price_monthly', 12, 2);
            $table->integer('min_cohort_size')->default(50); // 244.1 privacy cohort gate
            $table->string('status')->default('ACTIVE'); // ACTIVE, DEPRECATED, TERMINATED
            $table->timestamps();
        });

        Schema::create('data_sharing_agreements', function (Blueprint $table) {
            $table->id();
            $table->string('agreement_code')->unique();
            $table->string('partner_id')->index();
            $table->string('contract_id')->index();
            $table->json('allowed_fields_json'); // 244.2 field-level scope
            $table->boolean('consent_active')->default(true);
            $table->string('status')->default('ACTIVE'); // ACTIVE, REVOKED, EXPIRED
            $table->string('violation_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('data_clean_room_runs', function (Blueprint $table) {
            $table->id();
            $table->string('clean_room_code')->unique();
            $table->string('party_a_id');
            $table->string('party_b_id');
            $table->string('computation_type'); // PSI_INTERSECTION, AGGREGATE_OVERLAP
            $table->integer('output_cohort_count');
            $table->integer('raw_rows_exposed')->default(0); // MUST ALWAYS BE 0 (244.3, 244.5)
            $table->boolean('anti_reidentification_passed')->default(true);
            $table->boolean('approved_for_export')->default(false);
            $table->timestamps();
        });

        Schema::create('data_product_monetization_ledger', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_code')->unique();
            $table->string('product_code')->index();
            $table->decimal('gross_revenue_usd', 12, 2);
            $table->decimal('compute_cogs_usd', 12, 2);
            $table->decimal('net_margin_usd', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_product_monetization_ledger');
        Schema::dropIfExists('data_clean_room_runs');
        Schema::dropIfExists('data_sharing_agreements');
        Schema::dropIfExists('data_product_catalog');
    }
};
