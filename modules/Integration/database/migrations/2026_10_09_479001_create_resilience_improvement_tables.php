<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('int_enterprise_resilience_indexes', function (Blueprint $table) {
            $table->id();
            $table->string('domain_code')->unique();
            $table->decimal('recovery_capability_score', 5, 2);
            $table->decimal('redundancy_score', 5, 2);
            $table->decimal('diversity_score', 5, 2);
            $table->decimal('learning_rate_score', 5, 2);
            $table->decimal('composite_resilience_index', 5, 2); // 479.1
            $table->boolean('empirically_drill_verified')->default(false); // 479.5 edge case
            $table->timestamps();
        });

        Schema::create('int_continuous_improvement_ideas', function (Blueprint $table) {
            $table->id();
            $table->string('idea_code')->unique();
            $table->string('domain_code');
            $table->text('idea_description');
            $table->date('aging_sla_due_date'); // 479.6 risk
            $table->string('status')->default('intake'); // intake, standardized, closed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('int_continuous_improvement_ideas');
        Schema::dropIfExists('int_enterprise_resilience_indexes');
    }
};
