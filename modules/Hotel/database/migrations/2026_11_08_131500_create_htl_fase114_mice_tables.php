<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Leads Table
        if (! Schema::hasTable('htl_mice_leads')) {
            Schema::create('htl_mice_leads', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('lead_code', 32)->unique();
                $table->string('organizer_name');
                $table->string('company_name');
                $table->string('email');
                $table->string('phone', 32);
                $table->string('event_type', 32); // CONFERENCE, WEDDING, EXHIBITION, GALA_DINNER
                $table->unsignedInteger('expected_pax')->default(100);
                $table->unsignedBigInteger('estimated_budget_idr')->default(0);
                $table->date('tentative_date');
                $table->string('status', 32)->default('NEW')->index('htl_lead_status_idx');
                $table->string('created_by_reference', 64)->default('USR-SALES-01');
                $table->timestamps();
            });
        }

        // 2. Event Proposals Table
        if (! Schema::hasTable('htl_event_proposals')) {
            Schema::create('htl_event_proposals', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('proposal_code', 32)->unique();
                $table->uuid('lead_id');
                $table->string('title');
                // Revenue Breakdown
                $table->unsignedBigInteger('hall_rental_price_idr')->default(0);
                $table->unsignedBigInteger('fnb_price_idr')->default(0);
                $table->unsignedBigInteger('av_price_idr')->default(0);
                $table->unsignedBigInteger('room_price_idr')->default(0);
                $table->unsignedBigInteger('booth_price_idr')->default(0);
                $table->unsignedBigInteger('other_price_idr')->default(0);
                $table->unsignedBigInteger('total_revenue_idr')->default(0);
                // Direct Cost Breakdown
                $table->unsignedBigInteger('food_cogs_idr')->default(0);
                $table->unsignedBigInteger('bev_cogs_idr')->default(0);
                $table->unsignedBigInteger('labor_cost_idr')->default(0);
                $table->unsignedBigInteger('av_cost_idr')->default(0);
                $table->unsignedBigInteger('utility_cost_idr')->default(0);
                $table->unsignedBigInteger('other_cost_idr')->default(0);
                $table->unsignedBigInteger('total_direct_cost_idr')->default(0);
                // Margin Metrics
                $table->decimal('gross_margin_percent', 6, 2)->default(0.00);
                $table->string('status', 32)->default('DRAFT')->index('htl_prop_status_idx');
                $table->dateTime('timelock_expires_at')->nullable();
                $table->string('signature_hash', 64)->nullable();
                $table->string('created_by_reference', 64)->default('USR-SALES-01');
                $table->timestamps();

                $table->foreign('lead_id')->references('id')->on('htl_mice_leads')->cascadeOnDelete();
            });
        }

        // 3. Extend htl_mice_contracts with Fase 114 columns
        if (Schema::hasTable('htl_mice_contracts')) {
            Schema::table('htl_mice_contracts', function (Blueprint $table) {
                if (! Schema::hasColumn('htl_mice_contracts', 'proposal_id')) {
                    $table->uuid('proposal_id')->nullable()->after('event_code');
                }
                if (! Schema::hasColumn('htl_mice_contracts', 'is_bridal_suite_complimentary')) {
                    $table->boolean('is_bridal_suite_complimentary')->default(false)->after('status');
                }
                if (! Schema::hasColumn('htl_mice_contracts', 'signing_date')) {
                    $table->date('signing_date')->nullable()->after('event_date');
                }
                if (! Schema::hasColumn('htl_mice_contracts', 'event_start_date')) {
                    $table->date('event_start_date')->nullable()->after('signing_date');
                }
                if (! Schema::hasColumn('htl_mice_contracts', 'event_end_date')) {
                    $table->date('event_end_date')->nullable()->after('event_start_date');
                }
                if (! Schema::hasColumn('htl_mice_contracts', 'signature_hash')) {
                    $table->string('signature_hash', 64)->nullable()->after('is_bridal_suite_complimentary');
                }
                if (! Schema::hasColumn('htl_mice_contracts', 'created_by_reference')) {
                    $table->string('created_by_reference', 64)->default('USR-SALES-01')->after('signature_hash');
                }
            });
        }

        // 4. Extend htl_room_block_allotments with Fase 114 columns
        if (Schema::hasTable('htl_room_block_allotments')) {
            Schema::table('htl_room_block_allotments', function (Blueprint $table) {
                if (! Schema::hasColumn('htl_room_block_allotments', 'check_out_date')) {
                    $table->date('check_out_date')->nullable()->after('check_in_date');
                }
                if (! Schema::hasColumn('htl_room_block_allotments', 'daily_rate_idr')) {
                    $table->unsignedBigInteger('daily_rate_idr')->default(1000000)->after('check_out_date');
                }
                if (! Schema::hasColumn('htl_room_block_allotments', 'h30_cutoff_date')) {
                    $table->date('h30_cutoff_date')->nullable()->after('release_deadline_date');
                }
                if (! Schema::hasColumn('htl_room_block_allotments', 'h14_cutoff_date')) {
                    $table->date('h14_cutoff_date')->nullable()->after('h30_cutoff_date');
                }
                if (! Schema::hasColumn('htl_room_block_allotments', 'h7_cutoff_date')) {
                    $table->date('h7_cutoff_date')->nullable()->after('h14_cutoff_date');
                }
                if (! Schema::hasColumn('htl_room_block_allotments', 'signature_hash')) {
                    $table->string('signature_hash', 64)->nullable()->after('status');
                }
            });
        }

        // 5. Banquet Event Orders Table (BEO Production Sheet)
        if (! Schema::hasTable('htl_banquet_beo_orders')) {
            Schema::create('htl_banquet_beo_orders', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('beo_code', 32)->unique();
                $table->uuid('mice_contract_id');
                $table->string('title');
                $table->string('meal_period', 32)->default('LUNCH');
                $table->unsignedInteger('guaranteed_pax');
                $table->unsignedInteger('prepared_pax');
                $table->decimal('safety_buffer_ratio', 4, 2)->default(1.05);
                $table->date('event_date');
                $table->decimal('event_duration_hours', 4, 1)->default(4.0);
                $table->unsignedBigInteger('hourly_labor_rate_idr')->default(50000);
                $table->unsignedBigInteger('total_food_cogs_idr')->default(0);
                $table->unsignedBigInteger('total_bev_cogs_idr')->default(0);
                $table->unsignedBigInteger('total_labor_cost_idr')->default(0);
                $table->string('status', 32)->default('CONFIRMED')->index('htl_beo_status_idx');
                $table->json('recipe_bom_json')->nullable();
                $table->string('signature_hash', 64)->nullable();
                $table->timestamps();

                $table->foreign('mice_contract_id')->references('id')->on('htl_mice_contracts')->cascadeOnDelete();
            });
        }

        // 6. Wedding Milestone Schedules Table
        if (! Schema::hasTable('htl_wedding_milestone_schedules')) {
            Schema::create('htl_wedding_milestone_schedules', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('mice_contract_id');
                $table->unsignedSmallInteger('milestone_index');
                $table->string('name');
                $table->decimal('percentage', 5, 2);
                $table->unsignedBigInteger('amount_idr');
                $table->date('due_date');
                $table->string('status', 32)->default('PENDING')->index('htl_ms_status_idx');
                $table->unsignedBigInteger('paid_amount_idr')->default(0);
                $table->dateTime('paid_at')->nullable();
                $table->string('payment_reference', 64)->nullable();
                $table->timestamps();

                $table->foreign('mice_contract_id')->references('id')->on('htl_mice_contracts')->cascadeOnDelete();
                $table->unique(['mice_contract_id', 'milestone_index'], 'htl_ms_contract_index_uq');
            });
        }

        // 7. Exhibition Booth Reservations Table
        if (! Schema::hasTable('htl_exhibition_booth_reservations')) {
            Schema::create('htl_exhibition_booth_reservations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('reservation_code', 32)->unique();
                $table->uuid('mice_contract_id');
                $table->string('booth_number', 32);
                $table->string('booth_type', 32)->default('SHELL_3X3');
                $table->integer('grid_x')->default(0);
                $table->integer('grid_y')->default(0);
                $table->unsignedSmallInteger('width_m')->default(3);
                $table->unsignedSmallInteger('length_m')->default(3);
                $table->decimal('electricity_kva', 4, 1)->default(0.5);
                $table->boolean('is_dedicated_lan')->default(false);
                $table->unsignedBigInteger('base_rental_idr')->default(15000000);
                $table->unsignedBigInteger('utility_charge_idr')->default(1500000);
                $table->unsignedBigInteger('total_charge_idr')->default(16500000);
                $table->string('status', 32)->default('CONFIRMED')->index('htl_booth_status_idx');
                $table->unsignedInteger('estimated_daily_footfall')->default(500);
                $table->timestamps();

                $table->foreign('mice_contract_id')->references('id')->on('htl_mice_contracts')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('htl_exhibition_booth_reservations');
        Schema::dropIfExists('htl_wedding_milestone_schedules');
        Schema::dropIfExists('htl_banquet_beo_orders');
        Schema::dropIfExists('htl_event_proposals');
        Schema::dropIfExists('htl_mice_leads');
    }
};
