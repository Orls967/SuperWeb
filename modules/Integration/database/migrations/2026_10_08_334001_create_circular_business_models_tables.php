<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circular_business_asset_leases', function (Blueprint $table) {
            $table->id();
            $table->string('contract_code')->unique();
            $table->string('asset_sku');
            $table->string('business_model'); // PRODUCT_AS_A_SERVICE, EQUIPMENT_LEASE, CIRCULAR_REPAIR
            $table->string('ownership_state'); // COMPANY_OWNED_LEASED, RETURNED_FOR_REFURBISHMENT, RETIRED_RECYCLED
            $table->decimal('monthly_subscription_usd', 15, 2);
            $table->decimal('deposit_held_usd', 15, 2)->default(0.00); // 334.3 & 334.4 Deposit liability
            $table->boolean('is_profitable')->default(true); // 334.5 Edge case
            $table->timestamps();
        });

        Schema::create('circular_material_recovery_evidences', function (Blueprint $table) {
            $table->id();
            $table->string('evidence_code')->unique();
            $table->string('contract_code')->index();
            $table->decimal('recovered_material_kg', 10, 2);
            $table->boolean('material_recovery_verified')->default(false); // 334.3 & 334.4
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circular_material_recovery_evidences');
        Schema::dropIfExists('circular_business_asset_leases');
    }
};
