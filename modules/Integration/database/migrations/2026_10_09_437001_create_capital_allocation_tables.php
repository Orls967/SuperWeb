<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_capital_investment_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('proposal_code')->unique();
            $table->string('project_name');
            $table->decimal('npv_amount', 18, 2);
            $table->decimal('irr_percent', 5, 2);
            $table->decimal('strategic_fit_score', 5, 2); // 437.1
            $table->decimal('weighted_score', 5, 2);
            $table->decimal('requested_capex', 18, 2);
            $table->decimal('approved_funding_release', 18, 2)->default(0.00); // 437.2, 437.4
            $table->boolean('independent_reviewer_approved')->default(false); // 437.6
            $table->boolean('post_investment_review_completed')->default(true); // 437.3, 437.4
            $table->string('status')->default('proposed'); // proposed, approved, tranche_released, review_required
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_capital_investment_proposals');
    }
};
