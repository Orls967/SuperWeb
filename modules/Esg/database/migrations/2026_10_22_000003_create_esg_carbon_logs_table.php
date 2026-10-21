<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('esg_carbon_logs')) {
            Schema::create('esg_carbon_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('domain_code', 32);
                $table->string('scope_level', 16);
                $table->string('activity_name', 128);
                $table->unsignedBigInteger('activity_volume');
                $table->string('unit_code', 16);
                $table->unsignedInteger('emission_factor_bps');
                $table->unsignedBigInteger('co2_emitted_kg');
                $table->string('reporting_period', 16);
                $table->timestamps();

                $table->index(['domain_code', 'scope_level', 'reporting_period']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('esg_carbon_logs');
    }
};
