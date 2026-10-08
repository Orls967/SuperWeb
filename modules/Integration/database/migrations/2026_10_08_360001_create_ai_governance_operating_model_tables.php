<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_governance_operating_inventories', function (Blueprint $table) {
            $table->id();
            $table->string('model_code')->unique();
            $table->string('model_name');
            $table->string('owner_domain'); // MINING, ESG, COMMERCE
            $table->string('risk_classification'); // HIGH_RISK, LOW_RISK
            $table->boolean('is_shadow_unregistered')->default(false); // 360.5 Edge case
            $table->boolean('remediation_in_progress')->default(false);
            $table->timestamp('attestation_expires_at');
            $table->boolean('inference_permitted')->default(true);
            $table->timestamps();
        });

        Schema::create('ai_governance_council_decisions', function (Blueprint $table) {
            $table->id();
            $table->string('decision_code')->unique();
            $table->string('model_code')->index();
            $table->string('council_action'); // APPROVE, QUARANTINE, RETIRE
            $table->boolean('action_tracked')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_governance_council_decisions');
        Schema::dropIfExists('ai_governance_operating_inventories');
    }
};
