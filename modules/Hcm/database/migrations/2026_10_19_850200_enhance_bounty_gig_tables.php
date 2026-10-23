<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gov_bounties', function (Blueprint $table) {
            $table->boolean('requires_high_risk_permit')->default(false)->after('required_certification');
            $table->string('embedded_insurance_policy_ref', 64)->nullable()->after('requires_high_risk_permit');
            $table->bigInteger('speed_bonus_idr')->default(0)->after('embedded_insurance_policy_ref');
            $table->decimal('unit_rating_score', 3, 2)->default(4.80)->after('speed_bonus_idr');
        });

        Schema::table('gov_bounty_claims', function (Blueprint $table) {
            $table->string('work_permit_code', 64)->nullable()->after('employee_id');
            $table->unsignedInteger('mobility_points_earned')->default(10)->after('work_permit_code');
        });

        Schema::table('gov_bounty_pofs', function (Blueprint $table) {
            $table->decimal('overtime_multiplier', 3, 2)->default(1.50)->after('actual_hours_worked');
            $table->string('photo_evidence_ref', 128)->nullable()->after('geo_location_hash');
        });
    }

    public function down(): void
    {
        Schema::table('gov_bounty_pofs', function (Blueprint $table) {
            $table->dropColumn(['overtime_multiplier', 'photo_evidence_ref']);
        });

        Schema::table('gov_bounty_claims', function (Blueprint $table) {
            $table->dropColumn(['work_permit_code', 'mobility_points_earned']);
        });

        Schema::table('gov_bounties', function (Blueprint $table) {
            $table->dropColumn([
                'requires_high_risk_permit',
                'embedded_insurance_policy_ref',
                'speed_bonus_idr',
                'unit_rating_score',
            ]);
        });
    }
};
