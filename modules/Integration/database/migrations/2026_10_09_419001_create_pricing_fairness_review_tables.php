<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_pricing_fairness_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_code')->unique();
            $table->string('differentiation_basis'); // cost, timing, volume, loyalty_tier
            $table->boolean('is_prohibited_basis')->default(false); // 419.1, 419.4 (gender, religion, race, vulnerable segment)
            $table->decimal('max_discount_depth_percent', 5, 2)->default(30.00); // 419.2
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('crm_personalized_offers', function (Blueprint $table) {
            $table->id();
            $table->string('offer_code')->unique();
            $table->string('customer_id');
            $table->string('differentiation_basis');
            $table->decimal('base_price', 18, 2);
            $table->decimal('discount_percent', 5, 2);
            $table->decimal('final_price', 18, 2);
            $table->text('transparency_breakdown_json'); // 419.3
            $table->boolean('fairness_approved')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_personalized_offers');
        Schema::dropIfExists('crm_pricing_fairness_rules');
    }
};
