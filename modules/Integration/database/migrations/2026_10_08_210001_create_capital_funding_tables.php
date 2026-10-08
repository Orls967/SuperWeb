<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 210.1: Capital structure & debt covenant ratio monitoring across 30 lines
        Schema::create('fin_capital_covenants', function (Blueprint $table) {
            $table->id();
            $table->string('covenant_code')->unique();
            $table->string('entity_code');
            $table->string('covenant_name'); // e.g. DEBT_TO_EQUITY, DSCR
            $table->decimal('max_allowed_threshold', 8, 2);
            $table->decimal('actual_ratio_value', 8, 2);
            $table->boolean('covenant_breached')->default(false);
            $table->timestamps();
        });

        // 210.3: Dividend and capital distribution policies tested against retained earnings
        Schema::create('fin_capital_distributions', function (Blueprint $table) {
            $table->id();
            $table->string('distribution_code')->unique();
            $table->string('entity_code');
            $table->decimal('available_retained_profit_idr', 18, 2);
            $table->decimal('proposed_distribution_idr', 18, 2);
            $table->boolean('solvency_test_passed')->default(true);
            $table->string('approval_status')->default('APPROVED');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_capital_distributions');
        Schema::dropIfExists('fin_capital_covenants');
    }
};
