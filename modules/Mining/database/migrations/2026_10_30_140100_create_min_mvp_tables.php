<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('min_sites')) {
            Schema::table('min_sites', function (Blueprint $table) {
                if (! Schema::hasColumn('min_sites', 'timezone')) {
                    $table->string('timezone', 32)->default('Asia/Makassar')->after('commodity');
                }
                if (! Schema::hasColumn('min_sites', 'status')) {
                    $table->string('status', 32)->default('ACTIVE')->after('location');
                }
            });
        }

        if (! Schema::hasTable('min_areas')) {
            Schema::create('min_areas', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('site_id')->constrained('min_sites')->cascadeOnDelete();
                $table->foreignUuid('pit_id')->nullable()->constrained('min_pits')->nullOnDelete();
                $table->string('area_code')->unique();
                $table->string('name');
                $table->string('status', 32)->default('ACTIVE');
                $table->text('stop_reason')->nullable();
                $table->timestamp('stopped_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('min_equipment')) {
            Schema::table('min_equipment', function (Blueprint $table) {
                if (! Schema::hasColumn('min_equipment', 'tare_weight_kg')) {
                    $table->unsignedBigInteger('tare_weight_kg')->default(35000)->after('model');
                }
                if (! Schema::hasColumn('min_equipment', 'capacity_kg')) {
                    $table->unsignedBigInteger('capacity_kg')->default(50000)->after('tare_weight_kg');
                }
            });
        }

        if (! Schema::hasTable('min_shovel_slots')) {
            Schema::create('min_shovel_slots', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('equipment_id')->constrained('min_equipment')->cascadeOnDelete();
                $table->foreignUuid('area_id')->nullable()->constrained('min_areas')->nullOnDelete();
                $table->string('slot_code')->unique();
                $table->unsignedInteger('hour_window')->default(8);
                $table->unsignedInteger('capacity_trucks_per_hour')->default(10);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('min_stockpiles')) {
            Schema::create('min_stockpiles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('site_id')->constrained('min_sites')->cascadeOnDelete();
                $table->string('stockpile_code')->unique();
                $table->string('name');
                $table->unsignedBigInteger('total_kg')->default(0);
                $table->unsignedBigInteger('sum_kg_calories')->default(0);
                $table->unsignedBigInteger('sum_kg_ash_bps')->default(0);
                $table->unsignedBigInteger('sum_kg_sulfur_bps')->default(0);
                $table->unsignedBigInteger('sum_kg_moisture_bps')->default(0);
                $table->unsignedBigInteger('sum_kg_metal_bps')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('min_stockpile_movements')) {
            Schema::create('min_stockpile_movements', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('movement_uuid')->unique();
                $table->foreignUuid('site_id')->constrained('min_sites')->cascadeOnDelete();
                $table->foreignUuid('stockpile_id')->constrained('min_stockpiles')->cascadeOnDelete();
                $table->string('movement_type', 32);
                $table->string('direction', 8); // IN, OUT
                $table->unsignedBigInteger('weight_kg');
                $table->string('reference_type', 64)->nullable();
                $table->string('reference_id', 64)->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('min_weighbridge_tickets')) {
            Schema::table('min_weighbridge_tickets', function (Blueprint $table) {
                if (! Schema::hasColumn('min_weighbridge_tickets', 'ticket_uuid')) {
                    $table->string('ticket_uuid')->nullable()->after('id');
                }
                if (! Schema::hasColumn('min_weighbridge_tickets', 'pit_id')) {
                    $table->foreignUuid('pit_id')->nullable()->after('site_id')->constrained('min_pits')->nullOnDelete();
                }
                if (! Schema::hasColumn('min_weighbridge_tickets', 'area_id')) {
                    $table->foreignUuid('area_id')->nullable()->after('pit_id')->constrained('min_areas')->nullOnDelete();
                }
                if (! Schema::hasColumn('min_weighbridge_tickets', 'truck_id')) {
                    $table->foreignUuid('truck_id')->nullable()->after('area_id')->constrained('min_equipment')->nullOnDelete();
                }
                if (! Schema::hasColumn('min_weighbridge_tickets', 'driver_party_id')) {
                    $table->string('driver_party_id')->nullable()->after('truck_id');
                }
                if (! Schema::hasColumn('min_weighbridge_tickets', 'gross_weight_kg')) {
                    $table->unsignedBigInteger('gross_weight_kg')->default(0)->after('driver_party_id');
                }
                if (! Schema::hasColumn('min_weighbridge_tickets', 'tare_weight_kg')) {
                    $table->unsignedBigInteger('tare_weight_kg')->default(0)->after('gross_weight_kg');
                }
                if (! Schema::hasColumn('min_weighbridge_tickets', 'net_weight_kg')) {
                    $table->unsignedBigInteger('net_weight_kg')->default(0)->after('tare_weight_kg');
                }
                if (! Schema::hasColumn('min_weighbridge_tickets', 'grade_specs')) {
                    $table->json('grade_specs')->nullable()->after('net_weight_kg');
                }
                if (! Schema::hasColumn('min_weighbridge_tickets', 'is_overload')) {
                    $table->boolean('is_overload')->default(false)->after('net_weight_kg');
                }
                if (! Schema::hasColumn('min_weighbridge_tickets', 'destination_stockpile_id')) {
                    $table->foreignUuid('destination_stockpile_id')->nullable()->after('destination_stockpile')->constrained('min_stockpiles')->nullOnDelete();
                }
                if (! Schema::hasColumn('min_weighbridge_tickets', 'ticket_hash')) {
                    $table->string('ticket_hash', 64)->nullable()->after('destination_stockpile_id');
                }
                if (! Schema::hasColumn('min_weighbridge_tickets', 'prev_hash')) {
                    $table->string('prev_hash', 64)->nullable()->after('ticket_hash');
                }
                if (! Schema::hasColumn('min_weighbridge_tickets', 'weighed_at')) {
                    $table->timestamp('weighed_at')->nullable()->after('prev_hash');
                }
            });
        }

        if (Schema::hasTable('min_work_permits')) {
            Schema::table('min_work_permits', function (Blueprint $table) {
                if (! Schema::hasColumn('min_work_permits', 'area_id')) {
                    $table->foreignUuid('area_id')->nullable()->after('site_id')->constrained('min_areas')->nullOnDelete();
                }
                if (! Schema::hasColumn('min_work_permits', 'worker_party_id')) {
                    $table->string('worker_party_id')->nullable()->after('permit_type');
                }
                if (! Schema::hasColumn('min_work_permits', 'valid_from')) {
                    $table->timestamp('valid_from')->nullable()->after('worker_party_id');
                }
                if (! Schema::hasColumn('min_work_permits', 'approval_status')) {
                    $table->string('approval_status', 32)->default('APPROVED')->after('valid_until');
                }
            });
        }

        if (! Schema::hasTable('min_dispatch_plans')) {
            Schema::create('min_dispatch_plans', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('plan_uuid')->unique()->nullable();
                $table->foreignUuid('site_id')->constrained('min_sites')->cascadeOnDelete();
                $table->string('shift_code', 32);
                $table->string('algorithm', 32)->default('HUNGARIAN');
                $table->unsignedBigInteger('total_cost_seconds')->default(0);
                $table->text('certificate_json')->nullable();
                $table->string('status', 32)->default('COMMITTED');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('min_dispatch_assignments')) {
            Schema::create('min_dispatch_assignments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('dispatch_plan_id')->constrained('min_dispatch_plans')->cascadeOnDelete();
                $table->foreignUuid('truck_id')->nullable()->constrained('min_equipment')->nullOnDelete();
                $table->foreignUuid('shovel_slot_id')->nullable()->constrained('min_shovel_slots')->nullOnDelete();
                $table->unsignedBigInteger('estimated_cycle_seconds')->default(0);
                $table->boolean('is_override')->default(false);
                $table->text('override_reason')->nullable();
                $table->foreignId('override_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('min_blend_plans')) {
            Schema::create('min_blend_plans', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('plan_uuid')->unique();
                $table->foreignUuid('site_id')->constrained('min_sites')->cascadeOnDelete();
                $table->string('target_vessel_name');
                $table->unsignedBigInteger('target_min_kg');
                $table->unsignedBigInteger('target_max_kg');
                $table->text('target_specs_json')->nullable();
                $table->unsignedBigInteger('allocated_kg')->default(0);
                $table->text('allocations_json')->nullable();
                $table->text('certificate_json')->nullable();
                $table->string('status', 32)->default('DRAFT');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('min_reconciliation_runs')) {
            Schema::create('min_reconciliation_runs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('run_uuid')->unique();
                $table->foreignUuid('site_id')->constrained('min_sites')->cascadeOnDelete();
                $table->timestamp('period_start');
                $table->timestamp('period_end');
                $table->unsignedBigInteger('total_weighed_kg')->default(0);
                $table->unsignedBigInteger('total_stockpile_in_kg')->default(0);
                $table->unsignedBigInteger('total_stockpile_out_kg')->default(0);
                $table->unsignedBigInteger('total_shipped_kg')->default(0);
                $table->bigInteger('variance_kg')->default(0);
                $table->integer('variance_bps')->default(0);
                $table->string('status', 32)->default('BALANCED');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('min_reconciliation_investigations')) {
            Schema::create('min_reconciliation_investigations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('reconciliation_run_id')->constrained('min_reconciliation_runs')->cascadeOnDelete();
                $table->string('investigation_code')->unique();
                $table->string('status', 32)->default('OPEN');
                $table->text('resolution_notes')->nullable();
                $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('min_royalty_returns')) {
            Schema::create('min_royalty_returns', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('return_uuid')->unique();
                $table->foreignUuid('site_id')->constrained('min_sites')->cascadeOnDelete();
                $table->string('commodity', 32);
                $table->string('period_local', 7);
                $table->unsignedBigInteger('total_production_kg')->default(0);
                $table->unsignedBigInteger('benchmark_price_usd_cents_per_ton')->default(0);
                $table->unsignedBigInteger('exchange_rate_idr_per_usd_1e6')->default(0);
                $table->unsignedInteger('royalty_rate_bps')->default(0);
                $table->unsignedBigInteger('calculated_royalty_idr')->default(0);
                $table->unsignedBigInteger('finalized_royalty_idr')->nullable();
                $table->bigInteger('delta_royalty_idr')->default(0);
                $table->string('status', 32)->default('PROVISIONAL');
                $table->timestamps();

                $table->unique(['site_id', 'commodity', 'period_local']);
            });
        }

        if (Schema::hasTable('min_safety_incidents')) {
            Schema::table('min_safety_incidents', function (Blueprint $table) {
                if (! Schema::hasColumn('min_safety_incidents', 'incident_code')) {
                    $table->string('incident_code')->nullable()->after('id');
                }
                if (! Schema::hasColumn('min_safety_incidents', 'area_id')) {
                    $table->foreignUuid('area_id')->nullable()->after('site_id')->constrained('min_areas')->nullOnDelete();
                }
                if (! Schema::hasColumn('min_safety_incidents', 'area_stopped')) {
                    $table->boolean('area_stopped')->default(false)->after('severity');
                }
                if (! Schema::hasColumn('min_safety_incidents', 'occurred_at')) {
                    $table->timestamp('occurred_at')->nullable()->after('description');
                }
            });
        }

        if (! Schema::hasTable('min_contractor_evaluations')) {
            Schema::create('min_contractor_evaluations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('evaluation_uuid')->unique();
                $table->string('contractor_party_id');
                $table->foreignUuid('site_id')->constrained('min_sites')->cascadeOnDelete();
                $table->string('period', 7);
                $table->unsignedBigInteger('total_ton_km')->default(0);
                $table->unsignedBigInteger('fuel_consumed_liters')->default(0);
                $table->decimal('sfc_per_100_ton_km', 10, 2)->default(0);
                $table->decimal('baseline_median', 10, 2)->default(0);
                $table->decimal('baseline_mad', 10, 2)->default(0);
                $table->boolean('is_anomaly')->default(false);
                $table->unsignedBigInteger('penalty_amount_idr')->default(0);
                $table->boolean('payment_held')->default(false);
                $table->timestamps();

                $table->unique(['contractor_party_id', 'period', 'site_id'], 'min_contractor_eval_party_period_site_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('min_contractor_evaluations');
        Schema::dropIfExists('min_royalty_returns');
        Schema::dropIfExists('min_reconciliation_investigations');
        Schema::dropIfExists('min_reconciliation_runs');
        Schema::dropIfExists('min_blend_plans');
        Schema::dropIfExists('min_dispatch_assignments');
        Schema::dropIfExists('min_dispatch_plans');
        Schema::dropIfExists('min_stockpile_movements');
        Schema::dropIfExists('min_stockpiles');
        Schema::dropIfExists('min_shovel_slots');
        Schema::dropIfExists('min_areas');
    }
};
