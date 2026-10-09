<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('int_verifiable_enterprise_claims', function (Blueprint $table) {
            $table->id();
            $table->string('claim_code')->unique();
            $table->string('claim_type'); // quality, sustainability, financial, safety (484.1)
            $table->string('claim_statement');
            $table->string('evidence_hash'); // cryptographic hash (484.1, 484.4, 484.5 edge case)
            $table->boolean('public_verifiable')->default(true);
            $table->string('status')->default('draft'); // draft, published_verified, rejected
            $table->timestamps();
        });

        Schema::create('int_enterprise_trust_scores', function (Blueprint $table) {
            $table->id();
            $table->string('domain_code')->unique();
            $table->decimal('verification_coverage_percentage', 5, 2);
            $table->decimal('audit_pass_rate_percentage', 5, 2);
            $table->decimal('composite_trust_score', 5, 2); // 484.3
            $table->boolean('score_derived_from_verified_data_only')->default(true); // 484.6 risk
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('int_enterprise_trust_scores');
        Schema::dropIfExists('int_verifiable_enterprise_claims');
    }
};
