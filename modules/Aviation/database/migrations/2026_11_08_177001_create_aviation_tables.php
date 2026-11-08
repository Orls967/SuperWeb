<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Operators
        Schema::create('avi_operators', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->string('name', 128);
            $table->string('country', 8)->default('ID');
            $table->string('status', 32)->default('active');
            $table->timestamps();
        });

        // 2. Airports
        Schema::create('avi_airports', function (Blueprint $table) {
            $table->id();
            $table->string('iata_code', 3)->unique();
            $table->string('icao_code', 4)->unique();
            $table->string('name', 128);
            $table->string('city', 64);
            $table->string('province', 64);
            $table->string('island', 32);
            $table->integer('latitude_x10000')->default(0);
            $table->integer('longitude_x10000')->default(0);
            $table->integer('elevation_m')->default(0);
            $table->string('timezone', 32)->default('Asia/Jakarta');
            $table->boolean('slot_coordinated')->default(false);
            $table->integer('hourly_capacity')->default(30);
            $table->string('status', 32)->default('active');
            $table->timestamps();
        });

        // 3. Aircraft
        Schema::create('avi_aircraft', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->index('asset_id');
            $table->string('registration_mark', 16)->unique();
            $table->string('icao_type', 8);
            $table->string('model_name', 64);
            $table->foreignId('operator_id')->constrained('avi_operators')->cascadeOnDelete();
            $table->integer('cabin_y_capacity')->default(150);
            $table->integer('cabin_j_capacity')->default(12);
            $table->integer('mtow_kg')->default(79000);
            $table->integer('oew_kg')->default(41400);
            $table->integer('cg_forward_limit_mm')->default(8600);
            $table->integer('cg_aft_limit_mm')->default(9600);
            $table->integer('fuel_capacity_kg')->default(20000);
            $table->integer('total_flight_minutes')->default(0);
            $table->integer('total_flight_cycles')->default(0);
            $table->date('airworthiness_expires_at');
            $table->integer('a_check_due_minutes')->default(36000);
            $table->integer('a_check_current_minutes')->default(0);
            $table->string('status', 32)->default('active');
            $table->timestamps();
        });

        // 4. Routes
        Schema::create('avi_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('origin_airport_id')->constrained('avi_airports')->cascadeOnDelete();
            $table->foreignId('destination_airport_id')->constrained('avi_airports')->cascadeOnDelete();
            $table->string('flight_number', 16);
            $table->integer('distance_km');
            $table->integer('scheduled_duration_minutes');
            $table->bigInteger('base_fare_minor');
            $table->string('currency', 3)->default('IDR');
            $table->string('status', 32)->default('active');
            $table->timestamps();
        });

        // 5. Slots
        Schema::create('avi_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('airport_id')->constrained('avi_airports')->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained('avi_operators')->cascadeOnDelete();
            $table->string('season_code', 16);
            $table->integer('day_of_week')->default(1);
            $table->string('time_window_start', 8);
            $table->string('time_window_end', 8);
            $table->boolean('is_historic')->default(false);
            $table->integer('usage_rate_bps')->default(10000);
            $table->string('status', 32)->default('confirmed');
            $table->timestamps();
        });

        // 6. Slot Requests
        Schema::create('avi_slot_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('airport_id')->constrained('avi_airports')->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained('avi_operators')->cascadeOnDelete();
            $table->string('season_code', 16);
            $table->integer('requested_time');
            $table->boolean('is_new_entrant')->default(false);
            $table->string('status', 32)->default('submitted');
            $table->foreignId('allocated_slot_id')->nullable()->constrained('avi_slots')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 7. Slot Request Transitions
        Schema::create('avi_slot_request_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_request_id')->constrained('avi_slot_requests')->cascadeOnDelete();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->string('trigger', 64);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // 8. Flights
        Schema::create('avi_flights', function (Blueprint $table) {
            $table->id();
            $table->string('flight_number', 16);
            $table->foreignId('operator_id')->constrained('avi_operators')->cascadeOnDelete();
            $table->foreignId('aircraft_id')->nullable()->constrained('avi_aircraft')->nullOnDelete();
            $table->foreignId('route_id')->constrained('avi_routes')->cascadeOnDelete();
            $table->foreignId('origin_airport_id')->constrained('avi_airports')->cascadeOnDelete();
            $table->foreignId('destination_airport_id')->constrained('avi_airports')->cascadeOnDelete();
            $table->dateTime('departure_time');
            $table->dateTime('arrival_time');
            $table->foreignId('departure_slot_id')->nullable()->constrained('avi_slots')->nullOnDelete();
            $table->foreignId('arrival_slot_id')->nullable()->constrained('avi_slots')->nullOnDelete();
            $table->string('status', 32)->default('planned');
            $table->dateTime('actual_departure')->nullable();
            $table->dateTime('actual_arrival')->nullable();
            $table->integer('fuel_burn_kg')->default(0);
            $table->unsignedBigInteger('load_sheet_id')->nullable();
            $table->index('load_sheet_id');
            $table->timestamps();
        });

        // 9. Flight Transitions
        Schema::create('avi_flight_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flight_id')->constrained('avi_flights')->cascadeOnDelete();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->string('trigger', 64);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // 10. Seat Inventories
        Schema::create('avi_seat_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flight_id')->constrained('avi_flights')->cascadeOnDelete();
            $table->string('cabin', 4); // Y, J
            $table->integer('total_capacity')->default(150);
            $table->integer('held_seats')->default(0);
            $table->integer('booked_seats')->default(0);
            $table->integer('oversell_limit_seats')->default(0);
            $table->timestamps();

            $table->unique(['flight_id', 'cabin']);
        });

        // 11. Seat Holds
        Schema::create('avi_seat_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flight_id')->constrained('avi_flights')->cascadeOnDelete();
            $table->string('cabin', 4);
            $table->integer('seats_count');
            $table->bigInteger('fare_minor');
            $table->string('currency', 3)->default('IDR');
            $table->dateTime('expires_at');
            $table->string('idempotency_key', 128)->unique();
            $table->boolean('is_confirmed')->default(false);
            $table->boolean('is_released')->default(false);
            $table->timestamps();
        });

        // 12. Tickets
        Schema::create('avi_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 32)->unique();
            $table->foreignId('flight_id')->constrained('avi_flights')->cascadeOnDelete();
            $table->foreignId('seat_hold_id')->nullable()->constrained('avi_seat_holds')->nullOnDelete();
            $table->unsignedBigInteger('passenger_party_id')->nullable();
            $table->index('passenger_party_id');
            $table->string('cabin', 4);
            $table->string('seat_assignment', 8)->nullable();
            $table->string('fare_basis', 16)->default('STD');
            $table->bigInteger('base_fare_minor');
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_fare_minor');
            $table->string('currency', 3)->default('IDR');
            $table->string('status', 32)->default('held');
            $table->boolean('is_baggage_checked')->default(false);
            $table->integer('baggage_weight_g')->default(0);
            $table->text('pii_name_encrypted')->nullable();
            $table->string('pii_blind_index', 64)->nullable()->index();
            $table->timestamps();
        });

        // 13. Ticket Transitions
        Schema::create('avi_ticket_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('avi_tickets')->cascadeOnDelete();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->string('trigger', 64);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // 14. Refunds
        Schema::create('avi_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('avi_tickets')->cascadeOnDelete();
            $table->bigInteger('original_fare_minor');
            $table->bigInteger('penalty_minor')->default(0);
            $table->bigInteger('admin_fee_minor')->default(0);
            $table->bigInteger('tax_refund_minor')->default(0);
            $table->bigInteger('net_refund_minor');
            $table->string('currency', 3)->default('IDR');
            $table->string('reason', 128);
            $table->string('idempotency_key', 128)->unique();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamps();
        });

        // 15. Cargo Consignments
        Schema::create('avi_cargo_consignments', function (Blueprint $table) {
            $table->id();
            $table->string('consignment_number', 32)->unique();
            $table->foreignId('flight_id')->constrained('avi_flights')->cascadeOnDelete();
            $table->unsignedBigInteger('shipper_party_id')->nullable();
            $table->unsignedBigInteger('consignee_party_id')->nullable();
            $table->index('shipper_party_id');
            $table->index('consignee_party_id');
            $table->integer('weight_g');
            $table->bigInteger('volume_mm3')->default(0);
            $table->string('nature_of_goods', 128);
            $table->string('un_number', 16)->nullable();
            $table->string('dg_class', 16)->nullable();
            $table->boolean('is_dg')->default(false);
            $table->boolean('dg_cleared')->default(false);
            $table->integer('required_temperature_min_c10')->nullable();
            $table->integer('required_temperature_max_c10')->nullable();
            $table->string('uld_container_code', 16)->nullable();
            $table->string('status', 32)->default('booked');
            $table->string('custody_hash_chain', 64)->nullable();
            $table->timestamps();
        });

        // 16. Cargo Transitions
        Schema::create('avi_cargo_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_id')->constrained('avi_cargo_consignments')->cascadeOnDelete();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->string('trigger', 64);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // 17. Weight & Balance Records
        Schema::create('avi_weight_balance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flight_id')->constrained('avi_flights')->cascadeOnDelete();
            $table->foreignId('aircraft_id')->constrained('avi_aircraft')->cascadeOnDelete();
            $table->integer('total_weight_kg');
            $table->bigInteger('total_moment');
            $table->integer('cg_mm');
            $table->integer('fuel_weight_kg');
            $table->integer('fuel_arm_mm');
            $table->integer('oew_kg');
            $table->integer('oew_arm_mm');
            $table->integer('crew_weight_kg');
            $table->integer('crew_arm_mm');
            $table->integer('pax_weight_kg');
            $table->integer('pax_arm_mm');
            $table->integer('cargo_weight_kg');
            $table->integer('cargo_arm_mm');
            $table->boolean('is_within_envelope');
            $table->string('certificate_hash', 64);
            $table->timestamp('created_at')->useCurrent();
        });

        // 18. Maintenance Visits
        Schema::create('avi_maintenance_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aircraft_id')->constrained('avi_aircraft')->cascadeOnDelete();
            $table->string('visit_type', 32); // A_CHECK, C_CHECK, DEFECT, MOD
            $table->dateTime('scheduled_start');
            $table->dateTime('scheduled_end');
            $table->dateTime('actual_start')->nullable();
            $table->dateTime('actual_end')->nullable();
            $table->string('work_order_ref', 64)->nullable();
            $table->string('status', 32)->default('planned');
            $table->date('deferral_expiry')->nullable();
            $table->string('release_certificate_number', 64)->nullable();
            $table->timestamps();
        });

        // 19. Maintenance Transitions
        Schema::create('avi_maintenance_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_visit_id')->constrained('avi_maintenance_visits')->cascadeOnDelete();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->string('trigger', 64);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // 20. External Factors
        Schema::create('avi_external_factors', function (Blueprint $table) {
            $table->id();
            $table->string('factor_key', 64);
            $table->string('factor_value', 128);
            $table->dateTime('effective_from');
            $table->string('source', 128);
            $table->string('label', 32); // regulasi, asumsi_simulasi, fixture
            $table->timestamps();
        });

        // 21. Distributed Saga Instances
        Schema::create('avi_saga_instances', function (Blueprint $table) {
            $table->id();
            $table->string('saga_type', 64);
            $table->string('correlation_id', 128)->unique();
            $table->string('current_step', 64);
            $table->string('status', 32)->default('started'); // started, completed, failed, compensated
            $table->json('payload_json');
            $table->json('log_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avi_saga_instances');
        Schema::dropIfExists('avi_external_factors');
        Schema::dropIfExists('avi_maintenance_transitions');
        Schema::dropIfExists('avi_maintenance_visits');
        Schema::dropIfExists('avi_weight_balance_records');
        Schema::dropIfExists('avi_cargo_transitions');
        Schema::dropIfExists('avi_cargo_consignments');
        Schema::dropIfExists('avi_refunds');
        Schema::dropIfExists('avi_ticket_transitions');
        Schema::dropIfExists('avi_tickets');
        Schema::dropIfExists('avi_seat_holds');
        Schema::dropIfExists('avi_seat_inventories');
        Schema::dropIfExists('avi_flight_transitions');
        Schema::dropIfExists('avi_flights');
        Schema::dropIfExists('avi_slot_request_transitions');
        Schema::dropIfExists('avi_slot_requests');
        Schema::dropIfExists('avi_slots');
        Schema::dropIfExists('avi_routes');
        Schema::dropIfExists('avi_aircraft');
        Schema::dropIfExists('avi_airports');
        Schema::dropIfExists('avi_operators');
    }
};

