<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('int_enterprise_quality_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_code')->unique();
            $table->string('originating_line'); // e.g. LINE-WORKSHOP
            $table->string('impacted_line'); // e.g. LINE-CAR-RENTAL (cross-boundary 462.2)
            $table->string('designated_coordinator'); // 462.5 edge case
            $table->text('root_cause_analysis')->nullable();
            $table->text('system_level_fix')->nullable();
            $table->boolean('effectiveness_verified')->default(false); // 462.2, 462.4
            $table->string('status')->default('investigating'); // investigating, fix_implemented, verified_closed
            $table->timestamps();
        });

        Schema::create('int_quality_culture_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_code')->unique();
            $table->string('line_code');
            $table->boolean('is_non_punitive_submission')->default(true); // 462.3, 462.6
            $table->text('reported_observation');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('int_quality_culture_reports');
        Schema::dropIfExists('int_enterprise_quality_incidents');
    }
};
