<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 205.1 & 205.3: Vendor tiering and cross-line exposure concentration limits
        Schema::create('erm_vendor_criticalities', function (Blueprint $table) {
            $table->id();
            $table->string('vendor_code')->unique();
            $table->string('vendor_name');
            $table->string('criticality_tier'); // TIER_1_CRITICAL, TIER_2_SIGNIFICANT, TIER_3_STANDARD
            $table->decimal('max_allowed_concentration_idr', 18, 2);
            $table->decimal('current_aggregate_exposure_idr', 18, 2)->default(0.00);
            $table->boolean('concentration_breached')->default(false);
            $table->boolean('has_exit_continuity_playbook')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erm_vendor_criticalities');
    }
};
