<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('int_customer_value_propositions', function (Blueprint $table) {
            $table->id();
            $table->string('line_code')->unique();
            $table->string('proposition_title');
            $table->decimal('promised_sla_percentage', 5, 2);
            $table->decimal('actual_delivered_sla_percentage', 5, 2)->default(0.00);
            $table->decimal('value_gap_percentage', 5, 2)->default(0.00); // 463.2
            $table->boolean('has_prioritized_improvement_backlog')->default(false); // 463.5 edge case
            $table->timestamps();
        });

        Schema::create('int_value_selling_proof_points', function (Blueprint $table) {
            $table->id();
            $table->string('proof_code')->unique();
            $table->string('line_code');
            $table->string('quantified_claim');
            $table->boolean('evidence_verified')->default(false); // 463.3, 463.6 risk
            $table->string('status')->default('draft'); // draft, verified_approved, rejected
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('int_value_selling_proof_points');
        Schema::dropIfExists('int_customer_value_propositions');
    }
};
