<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plt_ux_research_studies', function (Blueprint $table) {
            $table->id();
            $table->string('study_code')->unique();
            $table->string('title');
            $table->text('findings_summary');
            $table->string('linked_backlog_feature'); // 435.1, 435.6 evidence for major UX change
            $table->timestamps();
        });

        Schema::create('plt_design_system_routes', function (Blueprint $table) {
            $table->id();
            $table->string('route_path')->unique();
            $table->decimal('design_system_adoption_percent', 5, 2); // 435.2
            $table->integer('custom_component_debt_count')->default(0); // 435.5
            $table->decimal('wcag_a11y_score', 5, 2); // 435.3
            $table->boolean('a11y_gate_passed')->default(false); // 435.3, 435.4
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plt_design_system_routes');
        Schema::dropIfExists('plt_ux_research_studies');
    }
};
