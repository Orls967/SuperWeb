<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_ecosystem_onboardings', function (Blueprint $table) {
            $table->id();
            $table->string('partner_code')->unique();
            $table->string('partner_name');
            $table->boolean('kyb_verified')->default(false);
            $table->boolean('contract_signed')->default(false);
            $table->boolean('certification_passed')->default(false);
            $table->string('onboarding_status'); // PENDING, REJECTED, GO_LIVE_APPROVED
            $table->string('rejection_reason')->nullable(); // 371.5 Edge case
            $table->boolean('can_reapply_after_remediation')->default(false);
            $table->timestamps();
        });

        Schema::create('partner_ecosystem_offboardings', function (Blueprint $table) {
            $table->id();
            $table->string('offboarding_code')->unique();
            $table->string('partner_code')->index();
            $table->boolean('api_access_revoked')->default(false); // 371.3, 371.4, 371.6 Risk
            $table->boolean('balances_settled')->default(false);
            $table->boolean('contract_closure_permitted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_ecosystem_offboardings');
        Schema::dropIfExists('partner_ecosystem_onboardings');
    }
};
