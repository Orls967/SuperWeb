<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_business_process_instances', function (Blueprint $table) {
            $table->id();
            $table->string('process_code')->unique();
            $table->string('workflow_type'); // CLAIM, ONBOARDING, PURCHASE_ORDER
            $table->string('current_state'); // DRAFT, REVIEW, APPROVED, CANCELLED
            $table->timestamps();
        });

        Schema::create('platform_process_cross_domain_sagas', function (Blueprint $table) {
            $table->id();
            $table->string('saga_code')->unique();
            $table->string('process_code')->index();
            $table->string('target_domain'); // FINANCE, INVENTORY
            $table->boolean('uses_contract_bridge')->default(true); // 367.5 Edge case
            $table->boolean('compensation_executed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_process_cross_domain_sagas');
        Schema::dropIfExists('platform_business_process_instances');
    }
};
