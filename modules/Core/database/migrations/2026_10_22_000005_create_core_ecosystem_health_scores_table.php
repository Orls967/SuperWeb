<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('core_ecosystem_health_scores')) {
            Schema::create('core_ecosystem_health_scores', function (Blueprint $table): void {
                $table->id();
                $table->string('entity_code', 32)->unique();
                $table->unsignedSmallInteger('financial_pillar_score');
                $table->unsignedSmallInteger('talent_pillar_score');
                $table->unsignedSmallInteger('esg_pillar_score');
                $table->unsignedSmallInteger('risk_pillar_score');
                $table->unsignedSmallInteger('composite_health_score');
                $table->string('status', 24)->default('EXCELLENT');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('core_ecosystem_health_scores');
    }
};
