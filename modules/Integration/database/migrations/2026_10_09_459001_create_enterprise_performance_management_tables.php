<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('int_enterprise_balanced_scorecards', function (Blueprint $table) {
            $table->id();
            $table->string('scorecard_code')->unique();
            $table->string('line_code'); // one of 30 lines
            $table->string('period'); // e.g. 2026-Q3
            $table->decimal('financial_score', 5, 2);
            $table->decimal('customer_score', 5, 2);
            $table->decimal('process_score', 5, 2);
            $table->decimal('people_score', 5, 2);
            $table->decimal('sustainability_score', 5, 2);
            $table->boolean('tradeoff_flagged')->default(false); // 459.5 conflict edge case
            $table->text('tradeoff_resolution_note')->nullable();
            $table->timestamps();
        });

        Schema::create('int_performance_review_actions', function (Blueprint $table) {
            $table->id();
            $table->string('action_code')->unique();
            $table->string('scorecard_code');
            $table->text('decision_description');
            $table->string('owner');
            $table->date('due_date');
            $table->boolean('is_escalated_aging')->default(false); // 459.6 risk
            $table->string('status')->default('open'); // open, closed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('int_performance_review_actions');
        Schema::dropIfExists('int_enterprise_balanced_scorecards');
    }
};
