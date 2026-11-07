<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Stope & Level Tambang Bawah Tanah (119.1)
        Schema::create('min_underground_stopes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->nullable()->constrained('min_sites')->cascadeOnDelete();
            $table->foreignUuid('pit_id')->nullable()->constrained('min_pits')->nullOnDelete();
            $table->string('stope_code', 32)->unique();
            $table->string('name', 128)->nullable();
            $table->string('level_name', 128)->nullable();
            $table->string('mining_method', 64)->nullable();
            $table->integer('level_depth_m')->nullable();
            $table->integer('rock_mass_rating')->default(65);
            $table->decimal('length_m', 8, 2)->nullable();
            $table->decimal('width_m', 8, 2)->nullable();
            $table->decimal('height_m', 8, 2)->nullable();
            $table->decimal('length_meters', 8, 2)->nullable();
            $table->decimal('width_meters', 8, 2)->nullable();
            $table->decimal('height_meters', 8, 2)->nullable();
            $table->decimal('volume_m3', 12, 2)->default(0.0);
            $table->decimal('planned_tonnage', 12, 2)->default(0.0);
            $table->decimal('actual_tonnage', 12, 2)->default(0.0);
            $table->decimal('extracted_tonnage', 12, 2)->default(0.0);
            $table->decimal('ore_grade', 6, 3)->nullable();
            $table->string('support_system', 255)->nullable();
            $table->string('status', 32)->default('DEVELOPMENT');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // 2. Cabang & Shaft Jaringan Ventilasi Bawah Tanah (119.1)
        Schema::create('min_ventilation_shafts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->nullable()->constrained('min_sites')->cascadeOnDelete();
            $table->foreignUuid('stope_id')->nullable()->constrained('min_underground_stopes')->nullOnDelete();
            $table->string('branch_code', 32)->nullable();
            $table->string('shaft_code', 32)->nullable();
            $table->string('name', 128)->nullable();
            $table->string('shaft_type', 32)->nullable();
            $table->string('from_node', 32)->nullable();
            $table->string('to_node', 32)->nullable();
            $table->decimal('resistance_r', 10, 6)->nullable();
            $table->decimal('initial_flow_q', 8, 3)->default(10.0);
            $table->decimal('depth_meters', 8, 2)->nullable();
            $table->decimal('diameter_meters', 8, 2)->nullable();
            $table->decimal('length_m', 8, 2)->nullable();
            $table->decimal('cross_section_area_m2', 8, 2)->nullable();
            $table->decimal('friction_factor_k', 8, 6)->default(0.012);
            $table->decimal('fan_installed_power_kw', 10, 2)->default(0.0);
            $table->decimal('fan_rated_airflow_m3_s', 8, 2)->default(0.0);
            $table->decimal('fan_operating_pressure_pa', 10, 2)->default(0.0);
            $table->decimal('fan_pressure_pa', 8, 2)->default(0.0);
            $table->string('level_connected', 128)->nullable();
            $table->boolean('is_fan_branch')->default(false);
            $table->boolean('is_exhaust_shaft')->default(false);
            $table->string('status', 32)->default('OPERATIONAL');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // 3. Log Simulasi Ventilasi Hardy Cross (119.1)
        Schema::create('min_ventilation_simulations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->nullable()->constrained('min_sites')->cascadeOnDelete();
            $table->string('simulation_code', 32)->unique();
            $table->integer('total_branches')->default(0);
            $table->integer('total_loops')->default(0);
            $table->integer('iterations_count')->default(0);
            $table->integer('iterations_to_converge')->default(0);
            $table->boolean('converged')->default(false);
            $table->boolean('is_converged')->default(false);
            $table->decimal('max_residual_flow', 8, 5)->nullable();
            $table->decimal('flow_balance_error_m3_s', 8, 5)->nullable();
            $table->decimal('intake_airflow_m3_s', 8, 2)->nullable();
            $table->decimal('return_airflow_m3_s', 8, 2)->nullable();
            $table->decimal('ch4_dilution_capacity_pct', 6, 3)->nullable();
            $table->decimal('co_concentration_ppm', 6, 2)->nullable();
            $table->decimal('total_fan_power_kw', 10, 2)->default(0.0);
            $table->json('fan_operating_point')->nullable();
            $table->json('branch_results')->nullable();
            $table->string('hmac_signature', 64)->nullable();
            $table->string('simulated_by', 64)->default('Sistem Otomasi Tambang');
            $table->string('status', 32)->default('VALID');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // 4. Proses Eksekusi Peledakan & Interlock Safety Perimeter (119.2)
        Schema::create('min_blast_clearance_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('blast_schedule_id')->nullable()->constrained('min_blast_schedules')->cascadeOnDelete();
            $table->string('run_code', 32)->unique();
            $table->string('pattern_code', 64)->nullable();
            $table->string('pit_name', 128)->nullable();
            $table->decimal('center_latitude', 10, 7)->nullable();
            $table->decimal('center_longitude', 10, 7)->nullable();
            $table->decimal('safety_radius_meters', 8, 2)->default(500.0);
            $table->integer('planned_holes_count')->default(0);
            $table->decimal('total_explosives_kg', 10, 2)->default(0.0);
            $table->integer('personnel_detected_count')->default(0);
            $table->integer('equipment_detected_count')->default(0);
            $table->boolean('all_personnel_evacuated')->default(false);
            $table->timestamp('siren_activated_at')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamp('detonated_at')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->string('officer_in_charge', 64)->nullable();
            $table->text('safety_notes')->nullable();
            $table->text('clearance_notes')->nullable();
            $table->string('abort_reason')->nullable();
            $table->string('hmac_signature', 64)->nullable();
            $table->string('status', 32)->default('DRAFT');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // 5. Evaluasi Fragmentasi Peledakan Model KCO (119.2)
        Schema::create('min_blast_fragmentation_evals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('blast_schedule_id')->nullable()->constrained('min_blast_schedules')->cascadeOnDelete();
            $table->foreignUuid('blast_clearance_run_id')->nullable()->constrained('min_blast_clearance_runs')->cascadeOnDelete();
            $table->string('eval_code', 32)->unique();
            $table->decimal('mean_fragment_size_d50_mm', 8, 2)->nullable();
            $table->decimal('median_size_x50_mm', 8, 2)->nullable();
            $table->decimal('passing_80_percent_d80_mm', 8, 2)->nullable();
            $table->decimal('uniformity_index_n', 6, 3)->nullable();
            $table->decimal('characteristic_size_xc_cm', 8, 2)->nullable();
            $table->decimal('powder_factor_kg_m3', 6, 3)->nullable();
            $table->decimal('oversize_percent', 5, 2)->nullable();
            $table->decimal('boulder_oversize_percent', 5, 2)->nullable();
            $table->decimal('fines_percent', 5, 2)->nullable();
            $table->decimal('fines_undersize_percent', 5, 2)->nullable();
            $table->integer('estimated_boulder_count')->default(0);
            $table->boolean('secondary_breaking_needed')->default(false);
            $table->decimal('burden_adjustment_m', 5, 2)->default(0.0);
            $table->decimal('spacing_adjustment_m', 5, 2)->default(0.0);
            $table->decimal('powder_factor_adjustment_kg_m3', 6, 3)->default(0.0);
            $table->string('status', 32)->default('OPTIMAL');
            $table->string('hmac_signature', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // 6. Insiden Lereng & Mitigasi Stabilitas Geoteknik (119.3)
        Schema::create('min_geotech_incidents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pit_id')->nullable()->constrained('min_pits')->cascadeOnDelete();
            $table->string('incident_code', 64)->unique();
            $table->string('location_sector', 128)->nullable();
            $table->string('severity_level', 32)->default('LOW');
            $table->string('sensor_code', 32)->nullable();
            $table->string('sensor_type', 64)->nullable();
            $table->string('status', 32)->default('MONITORING_NORMAL');
            $table->string('alert_level', 16)->default('GREEN');
            $table->decimal('displacement_mm', 8, 2)->nullable();
            $table->decimal('displacement_rate_mm_hour', 8, 2)->default(0.0);
            $table->decimal('cumulative_displacement_mm', 8, 2)->default(0.0);
            $table->decimal('velocity_mm_day', 8, 2)->nullable();
            $table->decimal('factor_of_safety', 5, 3)->default(1.500);
            $table->decimal('factor_of_safety_fos', 5, 3)->nullable();
            $table->decimal('pore_water_pressure_kpa', 8, 2)->default(0.0);
            $table->decimal('crack_aperture_mm', 8, 2)->default(0.0);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_evacuation_ordered')->default(false);
            $table->timestamp('evacuated_at')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamp('remediated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('geotech_engineer', 64)->nullable();
            $table->text('action_taken')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // 7. Survei Volumetrik Topografi Drone / Fotogrametri (119.4)
        Schema::create('min_quarry_drone_surveys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->nullable()->constrained('min_sites')->cascadeOnDelete();
            $table->foreignUuid('stockpile_id')->nullable()->constrained('min_stockpiles')->nullOnDelete();
            $table->string('quarry_pit_code', 64)->nullable();
            $table->string('survey_code', 64)->unique();
            $table->string('survey_type', 64)->nullable();
            $table->string('drone_model', 128)->nullable();
            $table->string('pilot_license_number', 64)->nullable();
            $table->integer('ground_control_points_count')->default(0);
            $table->decimal('horizontal_accuracy_cm', 6, 2)->nullable();
            $table->decimal('vertical_accuracy_cm', 6, 2)->nullable();
            $table->decimal('ground_sampling_distance_cm_px', 6, 2)->nullable();
            $table->bigInteger('total_points_count')->default(0);
            $table->decimal('surveyed_area_hectares', 8, 2)->nullable();
            $table->date('flight_date')->nullable();
            $table->integer('flight_altitude_m')->default(80);
            $table->integer('photos_count')->default(250);
            $table->decimal('point_cloud_density_pts_m2', 8, 2)->default(45.5);
            $table->decimal('calculated_volume_m3', 12, 2)->nullable();
            $table->decimal('calculated_cut_volume_m3', 12, 2)->nullable();
            $table->decimal('calculated_fill_volume_m3', 12, 2)->nullable();
            $table->decimal('calculated_net_volume_m3', 12, 2)->nullable();
            $table->decimal('estimated_density_t_m3', 6, 3)->default(1.650);
            $table->decimal('calculated_tonnage', 12, 2)->nullable();
            $table->decimal('calculated_tonnage_wmt', 12, 2)->nullable();
            $table->decimal('weighbridge_tonnage', 12, 2)->default(0.0);
            $table->decimal('variance_percent', 5, 2)->default(0.0);
            $table->string('status', 32)->default('PROCESSED');
            $table->string('pilot_name', 64)->default('Drone Chief Operator');
            $table->timestamp('surveyed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // 8. Pembacaan Hidrostatik Survei Draf Tongkang 6-Titik (119.5)
        Schema::create('min_barge_draft_surveys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('barge_shipment_id')->nullable()->constrained('min_barge_shipments')->cascadeOnDelete();
            $table->string('barge_code', 64)->nullable();
            $table->string('survey_code', 64)->unique();
            $table->string('survey_type', 32)->nullable();
            $table->decimal('draft_fp_m', 6, 3)->nullable();
            $table->decimal('draft_fs_m', 6, 3)->nullable();
            $table->decimal('draft_mp_m', 6, 3)->nullable();
            $table->decimal('draft_ms_m', 6, 3)->nullable();
            $table->decimal('draft_ap_m', 6, 3)->nullable();
            $table->decimal('draft_as_m', 6, 3)->nullable();
            $table->decimal('draft_forward_port', 6, 3)->nullable();
            $table->decimal('draft_forward_starboard', 6, 3)->nullable();
            $table->decimal('draft_midship_port', 6, 3)->nullable();
            $table->decimal('draft_midship_starboard', 6, 3)->nullable();
            $table->decimal('draft_aft_port', 6, 3)->nullable();
            $table->decimal('draft_aft_starboard', 6, 3)->nullable();
            $table->decimal('quarter_mean_draft_m', 6, 3)->nullable();
            $table->decimal('trim_meters', 6, 3)->nullable();
            $table->decimal('water_density_t_m3', 6, 4)->default(1.0250);
            $table->decimal('water_density_kg_l', 6, 4)->default(1.0250);
            $table->decimal('displacement_initial_ton', 10, 2)->nullable();
            $table->decimal('displacement_final_ton', 10, 2)->nullable();
            $table->decimal('displacement_tonnage_mt', 10, 2)->nullable();
            $table->decimal('calculated_cargo_ton', 10, 2)->nullable();
            $table->decimal('cargo_tonnage_wmt', 10, 2)->nullable();
            $table->decimal('weighbridge_cargo_ton', 10, 2)->nullable();
            $table->decimal('variance_percent', 5, 2)->nullable();
            $table->decimal('reconciliation_variance_pct', 5, 2)->nullable();
            $table->string('surveyor_company', 128)->nullable();
            $table->string('surveyor_name', 64)->nullable();
            $table->string('status', 32)->default('RECONCILED');
            $table->string('hmac_signature', 64)->nullable();
            $table->timestamp('surveyed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('min_barge_draft_surveys');
        Schema::dropIfExists('min_quarry_drone_surveys');
        Schema::dropIfExists('min_geotech_incidents');
        Schema::dropIfExists('min_blast_fragmentation_evals');
        Schema::dropIfExists('min_blast_clearance_runs');
        Schema::dropIfExists('min_ventilation_simulations');
        Schema::dropIfExists('min_ventilation_shafts');
        Schema::dropIfExists('min_underground_stopes');
    }
};
