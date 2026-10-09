<?php

declare(strict_types=1);

namespace Modules\Mining\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('min_work_permits', function (Blueprint $table) {
            $table->string('worker_party_id')->nullable()->after('supervisor_name');
            $table->string('allowed_pit_id')->nullable()->after('worker_party_id');
            $table->double('allowed_latitude', 10, 6)->nullable()->after('allowed_pit_id');
            $table->double('allowed_longitude', 10, 6)->nullable()->after('allowed_latitude');
            $table->double('allowed_radius_meters')->default(100.0)->after('allowed_longitude');
            $table->timestamp('valid_from')->nullable()->after('allowed_radius_meters');
        });

        Schema::create('min_fatigue_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('site_id');
            $table->string('operator_party_id');
            $table->string('equipment_id')->nullable();
            $table->double('work_hours_continuous')->default(0.0);
            $table->double('sleep_hours_prior')->default(8.0);
            $table->integer('fatigue_score'); // 0-100 (high = dangerously fatigued)
            $table->boolean('is_critical')->default(false);
            $table->string('recommended_action')->default('FIT_FOR_DUTY'); // FIT_FOR_DUTY, MANDATORY_REST, REASSIGN
            $table->timestamp('logged_at');
            $table->timestamps();

            $table->index(['operator_party_id', 'logged_at']);
        });

        Schema::create('min_safety_incidents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('site_id');
            $table->string('incident_type'); // NEAR_MISS, HAZARD_ID, PPE_NONCOMPLIANCE, LTI
            $table->string('severity')->default('LOW'); // LOW, MEDIUM, HIGH, CRITICAL
            $table->integer('leading_indicator_points')->default(10); // points contributing to proactive culture
            $table->string('reporter_party_id')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->text('description');
            $table->timestamp('reported_at');
            $table->timestamps();

            $table->index(['site_id', 'incident_type']);
        });

        Schema::create('min_contractor_safety_evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('contractor_party_id');
            $table->integer('safety_score'); // 0-100
            $table->integer('total_incidents')->default(0);
            $table->string('tier')->default('PREFERRED'); // PREFERRED, PROBATION, BLACKLISTED
            $table->boolean('tender_eligible')->default(true);
            $table->bigInteger('penalty_amount_minor')->default(0);
            $table->string('penalty_ledger_tx_id')->nullable();
            $table->timestamps();

            $table->index('contractor_party_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('min_contractor_safety_evaluations');
        Schema::dropIfExists('min_safety_incidents');
        Schema::dropIfExists('min_fatigue_logs');
        Schema::table('min_work_permits', function (Blueprint $table) {
            $table->dropColumn([
                'worker_party_id',
                'allowed_pit_id',
                'allowed_latitude',
                'allowed_longitude',
                'allowed_radius_meters',
                'valid_from',
            ]);
        });
    }
};
