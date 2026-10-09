<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_data_access_grants', function (Blueprint $table) {
            $table->id();
            $table->string('grant_code')->unique();
            $table->string('user_id');
            $table->string('domain_name'); // FINANCE, PAYROLL, MINING_TELEMETRY
            $table->string('access_tier'); // GENERAL, RESTRICTED_SENSITIVE
            $table->boolean('user_certified_literacy')->default(false); // 344.3, 344.4
            $table->timestamp('grant_expires_at'); // 344.1, 344.4 Time-bound grant
            $table->boolean('access_active')->default(true);
            $table->timestamps();
        });

        Schema::create('domain_data_product_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_code')->unique();
            $table->string('domain_name');
            $table->boolean('established_by_governance_council')->default(true); // 344.5 Edge case
            $table->boolean('ci_schema_quality_check_passed')->default(false); // 344.2 & 344.4
            $table->boolean('published_to_catalog')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_data_product_templates');
        Schema::dropIfExists('domain_data_access_grants');
    }
};
