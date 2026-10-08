<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulatory_control_gap_closures', function (Blueprint $table) {
            $table->id();
            $table->string('pipeline_code')->unique();
            $table->string('regulation_reference');
            $table->string('jurisdiction_country');
            $table->boolean('has_closure_evidence')->default(false); // 338.1 & 338.4
            $table->boolean('is_major_system_change')->default(false); // 338.5 Edge case
            $table->boolean('formal_replan_approved')->default(true);
            $table->boolean('gap_closed')->default(false);
            $table->timestamps();
        });

        Schema::create('litigation_enforcement_provisions', function (Blueprint $table) {
            $table->id();
            $table->string('case_code')->unique();
            $table->string('case_title');
            $table->decimal('accounting_provision_usd', 18, 2);
            $table->boolean('materiality_determination_documented')->default(true); // 338.3 & 338.4
            $table->boolean('legal_provision_approved')->default(false); // 338.4
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('litigation_enforcement_provisions');
        Schema::dropIfExists('regulatory_control_gap_closures');
    }
};
