<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_health_insurance_referral_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('journey_code')->unique();
            $table->string('patient_id');
            $table->boolean('medical_data_scoped')->default(true); // 382.1, 382.4, 382.6 Risk
            $table->boolean('unrestricted_sharing_prevented')->default(true);
            $table->timestamps();
        });

        Schema::create('global_health_disputed_claim_appeals', function (Blueprint $table) {
            $table->id();
            $table->string('claim_code')->unique();
            $table->decimal('claim_amount_usd', 12, 2);
            $table->boolean('is_clinically_valid')->default(true);
            $table->boolean('insurer_rejected')->default(false);
            $table->boolean('escrow_funded_to_protect_clinic')->default(false); // 382.4 & 382.5 Edge case
            $table->string('appeal_status'); // PENDING, ESCROWED_FOR_APPEAL
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_health_disputed_claim_appeals');
        Schema::dropIfExists('global_health_insurance_referral_contracts');
    }
};
