<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('int_test_data_environments', function (Blueprint $table) {
            $table->id();
            $table->string('env_code')->unique(); // e.g. ENV-DEV, ENV-STAGING
            $table->string('data_tier'); // synthetic, masked (474.1)
            $table->boolean('production_copy_approved')->default(false); // 474.1, 474.4
            $table->boolean('pii_leakage_detected')->default(false); // 474.3, 474.5 edge case
            $table->boolean('has_referential_integrity')->default(true); // 474.2
            $table->string('status')->default('clean'); // clean, pii_leak_purging
            $table->timestamps();
        });

        Schema::create('int_pii_remediation_purges', function (Blueprint $table) {
            $table->id();
            $table->string('purge_code')->unique();
            $table->string('env_code');
            $table->boolean('purged_and_regenerated')->default(false); // 474.5
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('int_pii_remediation_purges');
        Schema::dropIfExists('int_test_data_environments');
    }
};
