<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('int_innovation_funnel_projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_code')->unique();
            $table->string('title');
            $table->string('stage'); // idea, experiment, pilot, scale (460.1)
            $table->string('horizon'); // horizon_1, horizon_2, horizon_3 (460.2)
            $table->decimal('budget_allocated', 18, 2);
            $table->boolean('kill_criteria_triggered')->default(false); // 460.1
            $table->string('status')->default('active'); // active, scaled, killed_reallocated
            $table->timestamps();
        });

        Schema::create('int_ip_portfolio_assets', function (Blueprint $table) {
            $table->id();
            $table->string('ip_code')->unique();
            $table->string('ip_type'); // patent, trademark, copyright, trade_secret (460.3)
            $table->string('title');
            $table->date('maintenance_renewal_deadline');
            $table->boolean('deadline_alert_triggered')->default(false); // 460.5 edge case
            $table->string('status')->default('active'); // active, renewed, abandoned
            $table->text('abandonment_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('int_ip_portfolio_assets');
        Schema::dropIfExists('int_innovation_funnel_projects');
    }
};
