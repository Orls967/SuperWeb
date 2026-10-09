<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 197.1: Append-only decision log with input snapshot, model version, and replayability
        Schema::create('ai_decision_logs', function (Blueprint $table) {
            $table->id();
            $table->string('decision_code')->unique();
            $table->string('domain_code'); // L01 - L30
            $table->string('model_version');
            $table->json('input_snapshot');
            $table->json('output_decision');
            $table->json('top_contributing_features'); // 197.2 Explainability factors
            $table->decimal('disparity_metric', 6, 4)->default(0.0000); // 197.3 Fairness/bias
            $table->boolean('bias_flag')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_decision_logs');
    }
};
