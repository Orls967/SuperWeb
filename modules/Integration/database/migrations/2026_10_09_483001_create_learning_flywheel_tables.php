<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('int_enterprise_learning_loops', function (Blueprint $table) {
            $table->id();
            $table->string('loop_code')->unique();
            $table->string('originating_line');
            $table->text('insight_summary');
            $table->text('decision_taken');
            $table->text('outcome_observed');
            $table->boolean('practice_codified')->default(false); // 483.1 closed loop
            $table->date('review_due_date'); // 483.6 risk
            $table->string('status')->default('loop_open'); // loop_open, loop_closed, broken_remediated
            $table->timestamps();
        });

        Schema::create('int_cross_line_knowledge_exchanges', function (Blueprint $table) {
            $table->id();
            $table->string('exchange_code')->unique();
            $table->string('source_line');
            $table->string('target_line');
            $table->integer('participating_practitioners_count')->default(0); // 483.3, 483.4
            $table->decimal('adoption_rate_percentage', 5, 2)->default(0.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('int_cross_line_knowledge_exchanges');
        Schema::dropIfExists('int_enterprise_learning_loops');
    }
};
