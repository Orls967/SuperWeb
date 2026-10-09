<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('int_stakeholder_value_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('metric_code')->unique();
            $table->string('stakeholder_group'); // shareholders, customers, employees, partners, communities, regulators (478.1)
            $table->string('metric_name');
            $table->decimal('metric_value', 18, 4);
            $table->string('source_lineage_ref'); // 478.2, 478.4, 478.6 (automated cross-check narrative vs lineage)
            $table->timestamps();
        });

        Schema::create('int_stakeholder_feedback_actions', function (Blueprint $table) {
            $table->id();
            $table->string('feedback_code')->unique();
            $table->string('stakeholder_group');
            $table->text('feedback_summary');
            $table->boolean('is_accommodated')->default(true); // 478.5 edge case
            $table->text('non_accommodation_rationale')->nullable();
            $table->string('status')->default('tracked'); // tracked, closed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('int_stakeholder_feedback_actions');
        Schema::dropIfExists('int_stakeholder_value_metrics');
    }
};
