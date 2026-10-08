<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_customer_cross_line_entitlements', function (Blueprint $table) {
            $table->id();
            $table->string('entitlement_code')->unique();
            $table->string('customer_id');
            $table->boolean('customer_consent_active')->default(true); // 378.1 & 378.4
            $table->string('primary_policy_rule_id'); // 378.5 Edge case (single resolution policy)
            $table->boolean('conflict_resolved')->default(true);
            $table->timestamps();
        });

        Schema::create('global_customer_intercompany_redemptions', function (Blueprint $table) {
            $table->id();
            $table->string('redemption_code')->unique();
            $table->string('customer_id');
            $table->decimal('gross_redemption_amount', 12, 2);
            $table->decimal('entity_a_share_amount', 12, 2);
            $table->decimal('entity_b_share_amount', 12, 2);
            $table->boolean('intercompany_balanced')->default(true); // 378.2 & 378.4
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_customer_intercompany_redemptions');
        Schema::dropIfExists('global_customer_cross_line_entitlements');
    }
};
