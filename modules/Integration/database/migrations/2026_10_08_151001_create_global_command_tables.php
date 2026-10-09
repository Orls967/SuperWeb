<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grp_regions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // APAC, EMEA, AMERICAS
            $table->string('name');
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('grp_regional_hqs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('region_code');
            $table->string('legal_name');
            $table->string('country_code', 2); // SG, UK, US, etc
            $table->string('functional_currency', 3)->default('USD');
            $table->timestamps();
        });

        Schema::create('grp_country_ops', function (Blueprint $table) {
            $table->id();
            $table->string('region_code');
            $table->string('country_code', 2);
            $table->string('country_name');
            $table->string('status')->default('STUDY'); // STUDY, ENTRY, LIVE, EXIT
            $table->json('playbook_checklist')->nullable();
            $table->boolean('gate_passed')->default(false);
            $table->timestamps();
            $table->unique(['region_code', 'country_code']);
        });

        Schema::create('grp_fx_exposures', function (Blueprint $table) {
            $table->id();
            $table->string('region_code');
            $table->string('currency', 3);
            $table->decimal('exposure_amount', 18, 4)->default(0.0000);
            $table->decimal('hedged_amount', 18, 4)->default(0.0000);
            $table->decimal('rate_to_idr', 18, 4)->default(1.0000);
            $table->decimal('consolidated_idr', 18, 2)->default(0.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grp_fx_exposures');
        Schema::dropIfExists('grp_country_ops');
        Schema::dropIfExists('grp_regional_hqs');
        Schema::dropIfExists('grp_regions');
    }
};
