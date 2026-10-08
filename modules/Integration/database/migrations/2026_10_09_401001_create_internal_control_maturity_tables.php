<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_internal_controls', function (Blueprint $table) {
            $table->id();
            $table->string('control_code')->unique();
            $table->string('control_name');
            $table->string('frequency'); // continuous, daily, monthly, quarterly
            $table->string('owner');
            $table->string('evidence_source');
            $table->text('design_doc');
            $table->text('test_plan');
            $table->json('dependency_map')->nullable();
            $table->boolean('design_effective')->default(true);
            $table->boolean('operating_effective')->default(true);
            $table->integer('failure_streak')->default(0);
            $table->boolean('requires_redesign')->default(false); // 401.5 edge case
            $table->string('deficiency_rating')->nullable(); // none, low, medium, significant_deficiency, material_weakness
            $table->timestamps();
        });

        Schema::create('gov_control_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('control_id')->constrained('gov_internal_controls')->cascadeOnDelete();
            $table->string('period');
            $table->boolean('is_automated')->default(true);
            $table->boolean('passed')->default(true);
            $table->text('deficiency_notes')->nullable();
            $table->boolean('retest_required')->default(false);
            $table->string('attestation_by')->nullable(); // 401.6 dual review
            $table->string('reviewed_by')->nullable(); // 401.6 dual review
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_control_evaluations');
        Schema::dropIfExists('gov_internal_controls');
    }
};
