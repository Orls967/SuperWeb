<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('int_enterprise_process_maps', function (Blueprint $table) {
            $table->id();
            $table->string('process_code')->unique(); // e.g. OTC-01, P2P-01, H2R-01, I2R-01, I2C-01
            $table->string('process_name');
            $table->string('system_of_record'); // single source of truth (455.1, 455.2)
            $table->string('process_owner');
            $table->boolean('has_segregation_of_duties')->default(true); // creator != approver
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('int_process_deviations', function (Blueprint $table) {
            $table->id();
            $table->string('deviation_code')->unique();
            $table->string('process_code');
            $table->string('severity'); // low, medium, high, critical (455.3, 455.6)
            $table->text('deviation_details');
            $table->string('corrective_action_ticket')->nullable();
            $table->boolean('is_escalated')->default(false); // 455.6 aging SLA
            $table->string('status')->default('flagged'); // flagged, under_remediation, resolved
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('int_process_deviations');
        Schema::dropIfExists('int_enterprise_process_maps');
    }
};
