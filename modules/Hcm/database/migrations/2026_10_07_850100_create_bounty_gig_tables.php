<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 85.1 Internal bounties posted by business units
        Schema::create('gov_bounties', function (Blueprint $table) {
            $table->id();
            $table->string('bounty_code', 32)->unique();
            $table->string('business_unit_cost_center', 64); // RESTO_CK01, WMS_CIKARANG, MALL_DUTA, LOGISTICS_FLEET
            $table->string('title', 128);
            $table->text('description');
            $table->dateTime('shift_start');
            $table->dateTime('shift_end');
            $table->decimal('duration_hours', 4, 2);
            $table->bigInteger('hourly_rate_idr');
            $table->string('required_certification', 64)->nullable(); // K3_LOGISTICS, FOOD_SAFETY_HACCP, HEAVY_EQUIPMENT
            $table->string('status', 32)->default('OPEN'); // OPEN, CLAIMED, COMPLETED, CANCELLED
            $table->timestamps();
        });

        // 85.1 & 85.2 Claims with shift anti-collision
        Schema::create('gov_bounty_claims', function (Blueprint $table) {
            $table->id();
            $table->string('claim_code', 32)->unique();
            $table->unsignedBigInteger('bounty_id');
            $table->unsignedBigInteger('employee_id');
            $table->dateTime('claimed_at');
            $table->string('status', 32)->default('ASSIGNED'); // ASSIGNED, WORKED, PAID, CANCELLED
            $table->timestamps();

            $table->foreign('bounty_id')->references('id')->on('gov_bounties')->cascadeOnDelete();
        });

        // 85.1 & 85.3 Proof of work and supervisor sign-off
        Schema::create('gov_bounty_pofs', function (Blueprint $table) {
            $table->id();
            $table->string('pof_code', 32)->unique();
            $table->unsignedBigInteger('bounty_claim_id')->unique();
            $table->decimal('actual_hours_worked', 4, 2);
            $table->string('geo_location_hash', 64);
            $table->unsignedBigInteger('supervisor_user_id');
            $table->bigInteger('calculated_overtime_pay_idr');
            $table->string('status', 32)->default('APPROVED');
            $table->timestamps();

            $table->foreign('bounty_claim_id')->references('id')->on('gov_bounty_claims')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_bounty_pofs');
        Schema::dropIfExists('gov_bounty_claims');
        Schema::dropIfExists('gov_bounty_bounties');
        Schema::dropIfExists('gov_bounties');
    }
};
