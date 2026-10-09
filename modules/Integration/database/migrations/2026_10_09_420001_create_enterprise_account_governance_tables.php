<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_enterprise_umbrella_agreements', function (Blueprint $table) {
            $table->id();
            $table->string('agreement_code')->unique();
            $table->string('enterprise_client_name');
            $table->string('executive_sponsor');
            $table->decimal('total_contract_value', 18, 2);
            $table->decimal('health_index_score', 5, 2)->default(100.00); // 420.3
            $table->string('status')->default('active'); // active, cancelled, renewed
            $table->timestamps();
        });

        Schema::create('crm_enterprise_component_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('umbrella_id')->constrained('crm_enterprise_umbrella_agreements')->cascadeOnDelete();
            $table->string('component_order_code')->unique();
            $table->string('business_line'); // logistics, hotel, mining, fintech
            $table->decimal('line_billing_amount', 18, 2);
            $table->decimal('line_settlement_amount', 18, 2); // 420.2 & 420.4 consolidated billing reconciles
            $table->string('status')->default('active'); // active, managed_separately, completed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_enterprise_component_orders');
        Schema::dropIfExists('crm_enterprise_umbrella_agreements');
    }
};
