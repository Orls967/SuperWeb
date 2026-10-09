<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_strategy_nodes', function (Blueprint $table) {
            $table->id();
            $table->string('strategy_version')->default('2026-V1'); // 454.5 single active version
            $table->string('node_code')->unique();
            $table->string('node_type'); // vision, theme, objective, initiative, kpi (454.1)
            $table->string('title');
            $table->string('parent_node_code')->nullable(); // 454.4, 454.6 upward cascade link
            $table->string('assigned_owner');
            $table->decimal('allocated_funding', 18, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('gov_strategy_quarterly_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('review_code')->unique();
            $table->string('quarter'); // e.g. 2026-Q3
            $table->string('strategy_version');
            $table->decimal('reallocated_funding_amount', 18, 2)->default(0.00); // 454.3, 454.4
            $table->boolean('board_strategy_report_approved')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_strategy_quarterly_reviews');
        Schema::dropIfExists('gov_strategy_nodes');
    }
};
