<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 188.1: Unified RBAC + ABAC policy evaluator across 30 modules
        Schema::create('idp_access_policies', function (Blueprint $table) {
            $table->id();
            $table->string('policy_code')->unique();
            $table->string('role_code'); // e.g. FINANCE_MANAGER, STORE_OPERATOR
            $table->string('permission'); // READ, WRITE, APPROVE
            $table->string('allowed_tenant_scope'); // TENANT_SPECIFIC or ALL
            $table->string('allowed_region_scope'); // e.g. ID-JKT, ALL
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 188.2: Customer identity graph with consent & instant revocation
        Schema::create('idp_customer_identity_links', function (Blueprint $table) {
            $table->id();
            $table->string('link_code')->unique();
            $table->unsignedBigInteger('customer_id');
            $table->string('source_vertical'); // e.g. HOSPITALITY
            $table->string('target_vertical'); // e.g. HEALTHCARE
            $table->boolean('consent_granted')->default(false);
            $table->timestamp('consent_revoked_at')->nullable();
            $table->timestamps();
        });

        // 188.3: Vendor identity graph & consolidated cross-line credit exposure
        Schema::create('idp_vendor_group_exposures', function (Blueprint $table) {
            $table->id();
            $table->string('vendor_code')->unique();
            $table->decimal('group_credit_limit_idr', 18, 2);
            $table->decimal('current_consolidated_exposure_idr', 18, 2)->default(0.00);
            $table->boolean('is_limit_breached')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idp_vendor_group_exposures');
        Schema::dropIfExists('idp_customer_identity_links');
        Schema::dropIfExists('idp_access_policies');
    }
};
