<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_debt_covenant_monitors', function (Blueprint $table) {
            $table->id();
            $table->string('covenant_code')->unique();
            $table->string('lender_facility_name');
            $table->string('metric_name'); // e.g. Debt_to_EBITDA, DSCR
            $table->decimal('max_allowed_threshold', 10, 2);
            $table->decimal('current_metric_value', 10, 2);
            $table->decimal('headroom_percent', 5, 2); // 439.2
            $table->boolean('early_warning_triggered')->default(false); // 439.2, 439.5 (< 15% headroom)
            $table->boolean('is_in_breach')->default(false);
            $table->timestamps();
        });

        Schema::create('fin_investor_disclosures', function (Blueprint $table) {
            $table->id();
            $table->string('disclosure_code')->unique();
            $table->string('report_title');
            $table->boolean('materiality_cleared')->default(false); // 439.3, 439.4
            $table->boolean('internal_consistency_verified')->default(false); // 439.3, 439.6
            $table->boolean('legal_sign_off')->default(false);
            $table->string('status')->default('draft'); // draft, verified, published
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_investor_disclosures');
        Schema::dropIfExists('fin_debt_covenant_monitors');
    }
};
