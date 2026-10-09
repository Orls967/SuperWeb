<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_fraud_rulebooks', function (Blueprint $table) {
            $table->id();
            $table->string('rule_code')->unique();
            $table->string('business_cycle'); // procure-to-pay, order-to-cash, payroll, treasury, claims, etc.
            $table->string('rule_name');
            $table->string('scheme_type'); // split_invoice, vendor_collusion, loyalty_abuse, claim_stacking
            $table->string('version')->default('1.0');
            $table->boolean('is_active')->default(true);
            $table->decimal('precision_target', 5, 2)->default(90.00);
            $table->timestamps();
        });

        Schema::create('gov_fraud_red_team_exercises', function (Blueprint $table) {
            $table->id();
            $table->string('exercise_code')->unique();
            $table->string('business_cycle');
            $table->string('seeded_scheme');
            $table->boolean('detected')->default(false);
            $table->text('gap_notes')->nullable();
            $table->boolean('remediated')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_fraud_red_team_exercises');
        Schema::dropIfExists('gov_fraud_rulebooks');
    }
};
