<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 157.1: Reinsurance treaties
        Schema::create('reins_treaties', function (Blueprint $table) {
            $table->id();
            $table->string('treaty_code')->unique();
            $table->string('treaty_type'); // QUOTA_SHARE, EXCESS_OF_LOSS, STOP_LOSS
            $table->string('reinsurer_code');
            $table->decimal('cession_pct', 5, 2)->default(30.00); // 30% ceded
            $table->decimal('retention_limit', 18, 2)->default(500000000.00); // Max retention
            $table->decimal('reinsurance_commission_pct', 5, 2)->default(5.00);
            $table->timestamps();
        });

        // 157.2: Ceded / retained premium & claims accounting
        Schema::create('reins_cession_records', function (Blueprint $table) {
            $table->id();
            $table->string('treaty_code');
            $table->string('policy_number');
            $table->decimal('gross_premium', 18, 2);
            $table->decimal('ceded_premium', 18, 2);
            $table->decimal('retained_premium', 18, 2);
            $table->decimal('reinsurance_commission', 18, 2);
            $table->decimal('ceded_claim_recoverable', 18, 2)->default(0.00);
            $table->timestamps();
        });

        // 157.3: Solvency and capital adequacy (RBC / C-ROSS)
        Schema::create('reins_capital_adequacies', function (Blueprint $table) {
            $table->id();
            $table->string('evaluation_period', 10);
            $table->decimal('admitted_assets', 18, 2);
            $table->decimal('minimum_capital_required', 18, 2);
            $table->decimal('solvency_ratio_pct', 8, 2); // Target >= 120%
            $table->string('status')->default('SOLVENT'); // SOLVENT, CAPITAL_CALL
            $table->timestamps();
        });

        // 157.4: Catastrophe (CAT) model layers & cat bonds
        Schema::create('reins_cat_layers', function (Blueprint $table) {
            $table->id();
            $table->string('peril_type'); // EARTHQUAKE, FLOOD, PANDEMIC, TYPHOON
            $table->decimal('attachment_point', 18, 2);
            $table->decimal('exhaustion_point', 18, 2);
            $table->decimal('layer_limit', 18, 2);
            $table->decimal('reinstatement_premium', 18, 2)->default(0.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reins_cat_layers');
        Schema::dropIfExists('reins_capital_adequacies');
        Schema::dropIfExists('reins_cession_records');
        Schema::dropIfExists('reins_treaties');
    }
};
