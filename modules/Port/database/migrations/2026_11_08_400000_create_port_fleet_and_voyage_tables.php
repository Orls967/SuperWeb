<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Vessel Classification Societies (DNV, LR, BKI, ABS, NK, etc.)
        Schema::create('prt_fleet_classes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('society_name', 128);
            $table->string('class_notation', 128);
            $table->string('ice_class', 32)->nullable();
            $table->date('last_special_survey')->nullable();
            $table->date('next_special_survey')->nullable();
            $table->date('last_drydock_survey')->nullable();
            $table->date('next_drydock_survey')->nullable();
            $table->string('status', 32)->default('ACTIVE');
            $table->timestamps();
        });

        // 2. Fleet Vessels Master
        Schema::create('prt_fleet_vessels', function (Blueprint $table) {
            $table->id();
            $table->string('imo_number', 16)->unique();
            $table->string('vessel_name', 128);
            $table->string('call_sign', 32)->nullable();
            $table->string('mmsi', 32)->nullable();
            $table->string('flag_state', 64)->default('ID');
            $table->string('home_port', 64)->default('Tanjung Priok');
            $table->string('vessel_type', 64); // BULK_CARRIER, CONTAINER, PRODUCT_TANKER, TUG_SUPPLY, LPG_CARRIER
            $table->foreignId('fleet_class_id')->nullable()->constrained('prt_fleet_classes')->nullOnDelete();
            $table->year('built_year')->default(2020);
            $table->unsignedInteger('deadweight_tonnage')->default(50000); // DWT
            $table->unsignedInteger('gross_tonnage')->default(32000); // GRT
            $table->unsignedInteger('net_tonnage')->default(18000); // NRT
            $table->unsignedInteger('length_overall_cm')->default(19000); // LOA in cm
            $table->unsignedInteger('beam_cm')->default(3200); // Beam in cm
            $table->unsignedInteger('design_draft_cm')->default(1200); // Draft in cm
            $table->unsignedInteger('scantling_draft_cm')->default(1350);
            $table->unsignedInteger('main_engine_power_kw')->default(8500);
            $table->decimal('design_speed_knots', 6, 2)->default(14.50);
            $table->decimal('sfoc_design_grams_kwh', 8, 2)->default(175.50); // Specific Fuel Oil Consumption
            $table->string('fuel_types_supported', 128)->default('VLSFO,LSMGO'); // dual-fuel options
            $table->decimal('cii_reference_a', 12, 4)->default(4745.0000); // IMO reference formula params
            $table->decimal('cii_reference_c', 8, 4)->default(0.4220);
            $table->string('operational_status', 32)->default('IN_SERVICE'); // IN_SERVICE, IN_VOYAGE, ANCHORED, DRYDOCK, LAID_UP
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // 3. Bunker Tanks per Vessel
        Schema::create('prt_fleet_bunker_tanks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vessel_id')->constrained('prt_fleet_vessels')->cascadeOnDelete();
            $table->string('tank_code', 32);
            $table->string('tank_name', 64);
            $table->string('fuel_grade', 32); // VLSFO, LSMGO, MDO, LNG, BIO_B30
            $table->decimal('capacity_m3', 10, 2);
            $table->decimal('safe_capacity_m3', 10, 2); // 98% rule
            $table->decimal('current_rob_m3', 10, 2)->default(0.00); // Remaining On Board
            $table->decimal('current_rob_metric_tons', 10, 2)->default(0.00);
            $table->decimal('sounding_depth_cm', 8, 2)->default(0.00);
            $table->timestamps();
        });

        // 4. Statutory & Class Certificates
        Schema::create('prt_fleet_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vessel_id')->constrained('prt_fleet_vessels')->cascadeOnDelete();
            $table->string('certificate_type', 64); // SAFETY_CONSTRUCTION, SAFETY_EQUIPMENT, IOPP, IAPP, SMC, ISSC, MLC, TONNAGE
            $table->string('certificate_number', 64);
            $table->string('issuing_authority', 128); // e.g. BKI, Ditjen Hubla, DNV
            $table->date('issue_date');
            $table->date('expiry_date');
            $table->date('annual_endorsement_due')->nullable();
            $table->string('status', 32)->default('VALID'); // VALID, EXPIRING_SOON, EXPIRED, REVOKED
            $table->json('endorsement_history')->nullable();
            $table->timestamps();
        });

        // 5. Safe Manning & Crew Complement Requirements
        Schema::create('prt_fleet_crew_complements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vessel_id')->constrained('prt_fleet_vessels')->cascadeOnDelete();
            $table->string('minimum_safe_manning_cert_no', 64);
            $table->unsignedSmallInteger('deck_officers_required')->default(4);
            $table->unsignedSmallInteger('engine_officers_required')->default(4);
            $table->unsignedSmallInteger('deck_ratings_required')->default(6);
            $table->unsignedSmallInteger('engine_ratings_required')->default(4);
            $table->unsignedSmallInteger('catering_crew_required')->default(2);
            $table->unsignedSmallInteger('total_crew_onboard')->default(20);
            $table->boolean('stcw_compliant')->default(true);
            $table->date('last_inspected_at')->nullable();
            $table->timestamps();
        });

        // 6. Charter Parties (Fixtures)
        Schema::create('prt_fleet_charter_parties', function (Blueprint $table) {
            $table->id();
            $table->string('fixture_number', 64)->unique();
            $table->foreignId('vessel_id')->constrained('prt_fleet_vessels')->cascadeOnDelete();
            $table->string('charterer_name', 128);
            $table->string('charter_type', 32); // TIME_CHARTER, VOYAGE_CHARTER, BAREBOAT, COA
            $table->string('charter_form', 64)->default('GENCON_1994'); // GENCON_1994, NYPE_93, ASBATANKVOY, BALTIME
            $table->date('laycan_commencement');
            $table->date('laycan_cancellation');
            $table->decimal('freight_or_hire_rate', 14, 2); // USD/day for TC or USD/MT for VC
            $table->string('currency', 8)->default('USD');
            $table->decimal('laytime_allowed_hours', 10, 2)->default(72.00); // for voyage charter
            $table->string('laytime_terms', 64)->default('WWD_SHINC'); // WWD_SHINC, WWD_SHEX, REVERSIBLE
            $table->decimal('demurrage_rate_daily', 14, 2)->default(15000.00);
            $table->decimal('despatch_rate_daily', 14, 2)->default(7500.00); // usually 50% of demurrage
            $table->string('status', 32)->default('NEGOTIATING'); // NEGOTIATING, FIXTURE_AGREED, ON_HIRE, OFF_HIRE, RE_DELIVERED, SETTLED, DISPUTED
            $table->json('special_clauses')->nullable();
            $table->timestamps();
        });

        // 7. Freight Commitments & Contracts of Affreightment (COA)
        Schema::create('prt_fleet_freight_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charter_party_id')->constrained('prt_fleet_charter_parties')->cascadeOnDelete();
            $table->string('contract_number', 64)->unique();
            $table->string('cargo_description', 128);
            $table->decimal('quantity_mt', 12, 2);
            $table->decimal('tolerance_percentage', 5, 2)->default(5.00); // MOLOO (More Or Less Owner's Option)
            $table->decimal('freight_rate_per_mt', 12, 2);
            $table->decimal('total_freight_estimate', 14, 2);
            $table->decimal('advance_freight_paid', 14, 2)->default(0.00);
            $table->decimal('deadfreight_incurred', 14, 2)->default(0.00);
            $table->string('bill_of_lading_number', 64)->nullable();
            $table->string('payment_status', 32)->default('PENDING'); // PENDING, PARTIALLY_PAID, FULLY_PAID
            $table->timestamps();
        });

        // 8. Fleet Voyages (State Machine)
        Schema::create('prt_fleet_voyages', function (Blueprint $table) {
            $table->id();
            $table->string('voyage_code', 64)->unique();
            $table->foreignId('vessel_id')->constrained('prt_fleet_vessels')->cascadeOnDelete();
            $table->foreignId('charter_party_id')->nullable()->constrained('prt_fleet_charter_parties')->nullOnDelete();
            $table->string('voyage_number', 32);
            $table->string('voyage_type', 32)->default('TRAMP'); // TRAMP, LINER
            $table->string('origin_port', 64);
            $table->string('destination_port', 64);
            $table->string('load_port', 64);
            $table->string('discharge_port', 64);
            $table->timestamp('planned_start_at');
            $table->timestamp('planned_end_at');
            $table->timestamp('actual_start_at')->nullable();
            $table->timestamp('actual_end_at')->nullable();
            $table->decimal('planned_distance_nm', 10, 2)->default(1000.00);
            $table->decimal('actual_distance_nm', 10, 2)->default(0.00);
            $table->decimal('planned_speed_knots', 6, 2)->default(13.00);
            $table->string('status', 32)->default('PLANNED');
            // PLANNED, BALLAST_PASSAGE, ARRIVED_LOADPORT, TENDERED_NOR, COMMENCED_LOADING,
            // COMPLETED_LOADING, LADEN_PASSAGE, ARRIVED_DISCHPORT, DISCHARGING, COMPLETED_DISCHARGING, COMPLETED, CANCELLED
            $table->json('operational_notes')->nullable();
            $table->timestamps();
        });

        // 9. Voyage Legs (Ballast leg, Laden leg, Canal, Anchorage)
        Schema::create('prt_fleet_voyage_legs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voyage_id')->constrained('prt_fleet_voyages')->cascadeOnDelete();
            $table->unsignedSmallInteger('leg_sequence')->default(1);
            $table->string('leg_type', 32); // BALLAST, LADEN, CANAL_TRANSIT, RIVER_PASSAGE, ANCHORAGE
            $table->string('departure_waypoint', 64);
            $table->string('arrival_waypoint', 64);
            $table->decimal('distance_nm', 10, 2);
            $table->decimal('target_speed_knots', 6, 2);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('bunker_consumed_mt', 10, 2)->default(0.00);
            $table->string('status', 32)->default('PENDING'); // PENDING, IN_PROGRESS, COMPLETED
            $table->timestamps();
        });

        // 10. Daily Noon Reports (Master & Chief Engineer logs)
        Schema::create('prt_fleet_noon_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voyage_id')->constrained('prt_fleet_voyages')->cascadeOnDelete();
            $table->foreignId('vessel_id')->constrained('prt_fleet_vessels')->cascadeOnDelete();
            $table->date('report_date');
            $table->decimal('latitude', 10, 6);
            $table->decimal('longitude', 10, 6);
            $table->decimal('course_degrees', 6, 2)->default(0.00);
            $table->decimal('speed_log_knots', 6, 2)->default(12.00);
            $table->decimal('speed_gps_knots', 6, 2)->default(12.00);
            $table->decimal('distance_last_24h_nm', 8, 2);
            $table->decimal('wind_force_beaufort', 4, 1)->default(3.0);
            $table->string('wind_direction', 16)->default('NE');
            $table->decimal('sea_state_douglas', 4, 1)->default(2.0);
            $table->decimal('engine_rpm', 6, 2)->default(85.00);
            $table->decimal('engine_slip_percentage', 6, 2)->default(4.50);
            $table->decimal('vlsfo_consumed_24h_mt', 8, 2)->default(0.00);
            $table->decimal('lsmgo_consumed_24h_mt', 8, 2)->default(0.00);
            $table->decimal('vlsfo_rob_mt', 10, 2);
            $table->decimal('lsmgo_rob_mt', 10, 2);
            $table->decimal('cargo_temperature_celsius', 6, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        // 11. Sea Passages & Passage Planning
        Schema::create('prt_fleet_sea_passages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voyage_id')->constrained('prt_fleet_voyages')->cascadeOnDelete();
            $table->string('passage_plan_ref', 64);
            $table->json('waypoints'); // list of lat/lon and course
            $table->decimal('under_keel_clearance_min_m', 6, 2)->default(2.50);
            $table->boolean('ecdis_cross_track_limit_checked')->default(true);
            $table->boolean('marpol_special_area_transit')->default(false);
            $table->boolean('seca_transit')->default(false); // Sulphur Emission Control Area
            $table->timestamps();
        });

        // 12. Port Calls within Voyage
        Schema::create('prt_fleet_port_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voyage_id')->constrained('prt_fleet_voyages')->cascadeOnDelete();
            $table->string('port_code', 16); // e.g. IDJKT, IDSUB, SGSIN
            $table->string('port_name', 64);
            $table->string('call_purpose', 32); // LOADING, DISCHARGING, BUNKERING, REPAIRS
            $table->timestamp('estimated_arrival_at');
            $table->timestamp('actual_arrival_at')->nullable();
            $table->timestamp('nor_tendered_at')->nullable();
            $table->timestamp('nor_accepted_at')->nullable();
            $table->timestamp('all_fast_at')->nullable(); // berthed
            $table->timestamp('commenced_cargo_at')->nullable();
            $table->timestamp('completed_cargo_at')->nullable();
            $table->timestamp('actual_departure_at')->nullable();
            $table->decimal('arrival_draft_forward_m', 6, 2)->default(9.50);
            $table->decimal('arrival_draft_aft_m', 6, 2)->default(10.20);
            $table->decimal('departure_draft_forward_m', 6, 2)->default(10.50);
            $table->decimal('departure_draft_aft_m', 6, 2)->default(11.00);
            $table->string('call_status', 32)->default('SCHEDULED'); // SCHEDULED, ANCHORED, BERTHED, WORKING, COMPLETED
            $table->timestamps();
        });

        // 13. Weather Routing & Optimization Advice
        Schema::create('prt_fleet_weather_routings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voyage_id')->constrained('prt_fleet_voyages')->cascadeOnDelete();
            $table->string('advisory_source', 64)->default('FLEET_WEATHER_MET');
            $table->string('weather_pattern_alert', 64)->nullable(); // e.g. MALACCA_SQUALL, TROPICAL_DEPRESSION
            $table->decimal('significant_wave_height_m', 6, 2)->default(1.50);
            $table->decimal('recommended_speed_reduction_knots', 6, 2)->default(0.00);
            $table->decimal('diversion_distance_nm', 8, 2)->default(0.00);
            $table->decimal('estimated_fuel_saving_mt', 8, 2)->default(0.00);
            $table->boolean('route_diversion_accepted')->default(false);
            $table->timestamps();
        });

        // 14. Laytime & Demurrage Calculations
        Schema::create('prt_fleet_laytime_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charter_party_id')->constrained('prt_fleet_charter_parties')->cascadeOnDelete();
            $table->foreignId('port_call_id')->nullable()->constrained('prt_fleet_port_calls')->nullOnDelete();
            $table->string('calculation_reference', 64)->unique();
            $table->timestamp('nor_tendered_at');
            $table->timestamp('laytime_commenced_at');
            $table->timestamp('cargo_completed_at');
            $table->decimal('allowed_laytime_hours', 10, 2);
            $table->decimal('rain_interruption_hours', 8, 2)->default(0.00);
            $table->decimal('breakdown_interruption_hours', 8, 2)->default(0.00);
            $table->decimal('holiday_excluded_hours', 8, 2)->default(0.00);
            $table->decimal('net_laytime_used_hours', 10, 2);
            $table->decimal('demurrage_hours', 10, 2)->default(0.00);
            $table->decimal('despatch_hours', 10, 2)->default(0.00);
            $table->decimal('daily_demurrage_rate', 14, 2);
            $table->decimal('calculated_amount_usd', 14, 2)->default(0.00); // positive = demurrage payable to owner, negative = despatch to charterer
            $table->string('result_type', 32); // DEMURRAGE, DESPATCH, ON_TIME
            $table->string('settlement_status', 32)->default('PROPOSED'); // PROPOSED, AGREED, DISPUTED, SETTLED
            $table->json('statement_of_facts')->nullable();
            $table->timestamps();
        });

        // 15. Demurrage & Despatch Invoices
        Schema::create('prt_fleet_demurrage_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laytime_calculation_id')->constrained('prt_fleet_laytime_calculations')->cascadeOnDelete();
            $table->string('invoice_number', 64)->unique();
            $table->string('bill_to_party', 128);
            $table->decimal('amount_usd', 14, 2);
            $table->date('invoice_date');
            $table->date('due_date');
            $table->string('status', 32)->default('UNPAID'); // UNPAID, PAID, DISPUTED, CANCELLED
            $table->text('dispute_reason')->nullable();
            $table->timestamps();
        });

        // 16. Off-Hire Incidents (Time Charter)
        Schema::create('prt_fleet_off_hire_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charter_party_id')->constrained('prt_fleet_charter_parties')->cascadeOnDelete();
            $table->foreignId('vessel_id')->constrained('prt_fleet_vessels')->cascadeOnDelete();
            $table->string('incident_code', 64)->unique();
            $table->string('reason_category', 64); // ENGINE_BREAKDOWN, CREW_DEFICIENCY, DRYDOCK, ACCIDENT, PIRACY_DEVIATION
            $table->timestamp('off_hire_start_at');
            $table->timestamp('off_hire_end_at')->nullable();
            $table->decimal('duration_hours', 10, 2)->default(0.00);
            $table->decimal('daily_hire_deduction_rate', 14, 2);
            $table->decimal('total_hire_deducted_usd', 14, 2)->default(0.00);
            $table->decimal('bunker_consumed_during_off_hire_mt', 8, 2)->default(0.00);
            $table->decimal('bunker_reimbursement_usd', 14, 2)->default(0.00);
            $table->string('status', 32)->default('OPEN'); // OPEN, CLOSED, AGREED
            $table->timestamps();
        });

        // 17. Bunker Adjustments (On Delivery vs Re-Delivery)
        Schema::create('prt_fleet_bunker_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charter_party_id')->constrained('prt_fleet_charter_parties')->cascadeOnDelete();
            $table->string('event_type', 32); // DELIVERY, REDELIVERY
            $table->decimal('vlsfo_quantity_mt', 10, 2);
            $table->decimal('lsmgo_quantity_mt', 10, 2);
            $table->decimal('vlsfo_price_per_mt', 10, 2);
            $table->decimal('lsmgo_price_per_mt', 10, 2);
            $table->decimal('total_value_usd', 14, 2);
            $table->string('surveyor_company', 128)->nullable();
            $table->string('certificate_ref', 64)->nullable();
            $table->timestamps();
        });

        // 18. Marine Pilotage Requests
        Schema::create('prt_fleet_pilotage_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('port_call_id')->constrained('prt_fleet_port_calls')->cascadeOnDelete();
            $table->string('service_type', 32); // INWARD, OUTWARD, SHIFTING
            $table->timestamp('pilot_boarded_at')->nullable();
            $table->timestamp('pilot_disembarked_at')->nullable();
            $table->string('pilot_name', 64)->nullable();
            $table->decimal('pilotage_fee_usd', 12, 2)->default(1200.00);
            $table->string('status', 32)->default('REQUESTED'); // REQUESTED, ASSIGNED, COMPLETED, CANCELLED
            $table->timestamps();
        });

        // 19. Tugboat Assist Services
        Schema::create('prt_fleet_tug_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('port_call_id')->constrained('prt_fleet_port_calls')->cascadeOnDelete();
            $table->string('tug_name', 64);
            $table->unsignedSmallInteger('bollard_pull_tonnage')->default(45);
            $table->unsignedSmallInteger('hours_used')->default(2);
            $table->decimal('hourly_rate_usd', 10, 2)->default(650.00);
            $table->decimal('total_cost_usd', 12, 2);
            $table->string('service_phase', 32); // BERTHING, UNBERTHING, ESCORT
            $table->timestamps();
        });

        // 20. Berth Reservation / Window Allocation
        Schema::create('prt_fleet_berth_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('port_call_id')->constrained('prt_fleet_port_calls')->cascadeOnDelete();
            $table->string('berth_code', 32);
            $table->timestamp('window_start_at');
            $table->timestamp('window_end_at');
            $table->decimal('allocated_length_m', 8, 2);
            $table->decimal('max_draft_m', 6, 2);
            $table->string('booking_status', 32)->default('CONFIRMED'); // CONFIRMED, OCCUPIED, RELEASED, CANCELLED
            $table->timestamps();
        });

        // 21. Bunkering Operations (BDN & Fuel Sampling)
        Schema::create('prt_fleet_bunkering_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vessel_id')->constrained('prt_fleet_vessels')->cascadeOnDelete();
            $table->foreignId('port_call_id')->nullable()->constrained('prt_fleet_port_calls')->nullOnDelete();
            $table->string('bunker_stem_number', 64)->unique();
            $table->string('supplier_name', 128);
            $table->string('barge_name', 64);
            $table->string('fuel_grade', 32); // VLSFO_0_5, LSMGO_0_1, LNG
            $table->decimal('quantity_metric_tons', 10, 2);
            $table->decimal('density_at_15c_kg_m3', 8, 2)->default(991.00);
            $table->decimal('sulphur_content_percentage', 5, 2)->default(0.48);
            $table->decimal('viscosity_cst_at_50c', 8, 2)->default(380.00);
            $table->string('bunker_delivery_note_number', 64);
            $table->string('marpol_seal_number', 64);
            $table->timestamp('bunkering_started_at');
            $table->timestamp('bunkering_completed_at');
            $table->string('compliance_status', 32)->default('COMPLIANT'); // COMPLIANT, OFF_SPEC
            $table->timestamps();
        });

        // 22. Port Disbursements (PDA & FDA)
        Schema::create('prt_fleet_port_disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('port_call_id')->constrained('prt_fleet_port_calls')->cascadeOnDelete();
            $table->string('disbursement_type', 16); // PDA (Proforma), FDA (Final)
            $table->string('account_reference', 64);
            $table->decimal('port_dues_usd', 12, 2)->default(0.00);
            $table->decimal('pilotage_usd', 12, 2)->default(0.00);
            $table->decimal('towage_usd', 12, 2)->default(0.00);
            $table->decimal('berth_hire_usd', 12, 2)->default(0.00);
            $table->decimal('linesmen_usd', 12, 2)->default(0.00);
            $table->decimal('waste_reception_usd', 12, 2)->default(0.00);
            $table->decimal('agency_fee_usd', 12, 2)->default(0.00);
            $table->decimal('customs_immigration_usd', 12, 2)->default(0.00);
            $table->decimal('total_disbursement_usd', 14, 2);
            $table->decimal('variance_with_pda_usd', 12, 2)->default(0.00);
            $table->string('status', 32)->default('SUBMITTED'); // SUBMITTED, APPROVED, AUDITED, SETTLED
            $table->timestamps();
        });

        // 23. MARPOL Waste Disposals (Annex I, IV, V)
        Schema::create('prt_fleet_waste_disposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('port_call_id')->constrained('prt_fleet_port_calls')->cascadeOnDelete();
            $table->string('marpol_annex', 32); // ANNEX_I_OILY_BILGE, ANNEX_IV_SEWAGE, ANNEX_V_GARBAGE
            $table->decimal('quantity_m3', 8, 2);
            $table->string('reception_facility_name', 128);
            $table->string('receipt_certificate_number', 64);
            $table->timestamp('disposed_at');
            $table->timestamps();
        });

        // 24. General Average Cases (York-Antwerp Rules 2016)
        Schema::create('prt_fleet_general_average_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voyage_id')->constrained('prt_fleet_voyages')->cascadeOnDelete();
            $table->string('case_number', 64)->unique();
            $table->string('incident_description', 255);
            $table->timestamp('declared_at');
            $table->string('average_adjuster_firm', 128);
            $table->decimal('ship_contributory_value_usd', 16, 2);
            $table->decimal('cargo_contributory_value_usd', 16, 2);
            $table->decimal('freight_contributory_value_usd', 16, 2);
            $table->decimal('total_contributory_value_usd', 16, 2);
            $table->decimal('total_sacrifices_usd', 16, 2);
            $table->decimal('total_expenditures_usd', 16, 2);
            $table->decimal('general_average_loss_usd', 16, 2);
            $table->decimal('contribution_rate_percentage', 8, 4);
            $table->decimal('ship_contribution_usd', 16, 2);
            $table->decimal('cargo_contribution_usd', 16, 2);
            $table->decimal('freight_contribution_usd', 16, 2);
            $table->string('adjustment_status', 32)->default('DECLARED'); // DECLARED, ADJUSTING, SETTLED
            $table->timestamps();
        });

        // 25. Fleet Double-Entry Ledger Entries
        Schema::create('prt_fleet_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_code', 64)->unique();
            $table->foreignId('voyage_id')->nullable()->constrained('prt_fleet_voyages')->nullOnDelete();
            $table->foreignId('vessel_id')->nullable()->constrained('prt_fleet_vessels')->nullOnDelete();
            $table->string('account_code', 32); // 1000 Cash, 1200 Receivables, 2100 Payables, 4000 Freight Rev, 5000 Bunker Exp, 5100 Port Dues Exp
            $table->string('entry_type', 16); // DEBIT, CREDIT
            $table->decimal('amount', 14, 2);
            $table->string('currency', 8)->default('USD');
            $table->string('reference_type', 64); // CHARTER_PARTY, DISBURSEMENT, BUNKER_PURCHASE, DEMURRAGE, OFF_HIRE, GENERAL_AVERAGE
            $table->unsignedBigInteger('reference_id');
            $table->string('description', 255);
            $table->string('checksum', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prt_fleet_ledger_entries');
        Schema::dropIfExists('prt_fleet_general_average_cases');
        Schema::dropIfExists('prt_fleet_waste_disposals');
        Schema::dropIfExists('prt_fleet_port_disbursements');
        Schema::dropIfExists('prt_fleet_bunkering_operations');
        Schema::dropIfExists('prt_fleet_berth_bookings');
        Schema::dropIfExists('prt_fleet_tug_services');
        Schema::dropIfExists('prt_fleet_pilotage_requests');
        Schema::dropIfExists('prt_fleet_bunker_adjustments');
        Schema::dropIfExists('prt_fleet_off_hire_events');
        Schema::dropIfExists('prt_fleet_demurrage_invoices');
        Schema::dropIfExists('prt_fleet_laytime_calculations');
        Schema::dropIfExists('prt_fleet_weather_routings');
        Schema::dropIfExists('prt_fleet_port_calls');
        Schema::dropIfExists('prt_fleet_sea_passages');
        Schema::dropIfExists('prt_fleet_noon_reports');
        Schema::dropIfExists('prt_fleet_voyage_legs');
        Schema::dropIfExists('prt_fleet_voyages');
        Schema::dropIfExists('prt_fleet_freight_contracts');
        Schema::dropIfExists('prt_fleet_charter_parties');
        Schema::dropIfExists('prt_fleet_crew_complements');
        Schema::dropIfExists('prt_fleet_certificates');
        Schema::dropIfExists('prt_fleet_bunker_tanks');
        Schema::dropIfExists('prt_fleet_vessels');
        Schema::dropIfExists('prt_fleet_classes');
    }
};
