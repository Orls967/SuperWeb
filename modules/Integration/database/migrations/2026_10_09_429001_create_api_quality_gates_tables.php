<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plt_api_partner_certifications', function (Blueprint $table) {
            $table->id();
            $table->string('partner_code')->unique();
            $table->string('partner_name');
            $table->boolean('sandbox_suite_passed')->default(false); // 429.2, 429.5
            $table->boolean('schema_compatibility_passed')->default(false); // 429.1, 429.4
            $table->boolean('semantic_contract_approved')->default(false); // 429.6 risk
            $table->string('certificate_token')->nullable();
            $table->date('certificate_expiry_date')->nullable(); // 429.4 expiry
            $table->string('production_api_key')->nullable();
            $table->boolean('production_enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plt_api_partner_certifications');
    }
};
