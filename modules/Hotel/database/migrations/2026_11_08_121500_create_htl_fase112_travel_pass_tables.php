<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('htl_travel_passes')) {
            Schema::table('htl_travel_passes', function (Blueprint $table) {
                if (! Schema::hasColumn('htl_travel_passes', 'user_reference')) {
                    $table->string('user_reference')->nullable()->index();
                }
                if (! Schema::hasColumn('htl_travel_passes', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
                if (! Schema::hasColumn('htl_travel_passes', 'metadata')) {
                    $table->json('metadata')->nullable();
                }
            });
        } else {
            Schema::create('htl_travel_passes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('pass_number')->unique();
                $table->string('user_reference')->nullable()->index();
                $table->string('tier')->default('SILVER');
                $table->integer('cumulative_nights')->default(0);
                $table->bigInteger('cumulative_spend_idr')->default(0);
                $table->bigInteger('pts_balance')->default(0);
                $table->boolean('is_active')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('htl_travel_pass_point_batches')) {
            Schema::create('htl_travel_pass_point_batches', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('travel_pass_id')->constrained('htl_travel_passes')->cascadeOnDelete();
                $table->string('batch_number')->unique();
                $table->string('originating_line')->default('HTL_ROOM');
                $table->bigInteger('initial_pts');
                $table->bigInteger('remaining_pts');
                $table->integer('liability_per_pt_idr')->default(100);
                $table->bigInteger('total_liability_idr');
                $table->string('state')->default('ACTIVE');
                $table->string('reference_code')->nullable();
                $table->timestamp('earned_at');
                $table->timestamp('expires_at');
                $table->timestamp('breakage_recognized_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('htl_alliance_partners')) {
            Schema::create('htl_alliance_partners', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('partner_code')->unique();
                $table->string('partner_name');
                $table->string('category')->default('AIRLINE');
                $table->decimal('base_exchange_ratio', 8, 4)->default(10.0000);
                $table->decimal('transfer_fee_percentage', 5, 4)->default(0.0350);
                $table->integer('min_points_transfer')->default(1000);
                $table->integer('max_points_transfer_daily')->default(100000);
                $table->boolean('is_active')->default(true);
                $table->string('api_endpoint')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('htl_alliance_transfer_logs')) {
            Schema::create('htl_alliance_transfer_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('travel_pass_id')->constrained('htl_travel_passes')->cascadeOnDelete();
                $table->string('partner_code');
                $table->text('member_partner_account');
                $table->bigInteger('points_debited');
                $table->bigInteger('partner_units_credited');
                $table->bigInteger('transfer_fee_idr');
                $table->bigInteger('net_payable_idr');
                $table->string('status')->default('PENDING');
                $table->string('saga_reference')->nullable();
                $table->string('idempotency_key')->unique();
                $table->string('certificate_hash')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('htl_award_night_pricing_rules')) {
            Schema::create('htl_award_night_pricing_rules', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('property_reference')->index();
                $table->string('room_type_reference')->index();
                $table->integer('base_points_required')->default(15000);
                $table->bigInteger('marginal_cost_idr')->default(300000);
                $table->decimal('floor_multiplier', 4, 2)->default(1.15);
                $table->decimal('seasonality_multiplier', 4, 2)->default(1.00);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('htl_personalized_travel_campaigns')) {
            Schema::create('htl_personalized_travel_campaigns', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('travel_pass_id')->constrained('htl_travel_passes')->cascadeOnDelete();
                $table->string('campaign_code');
                $table->string('offer_title');
                $table->string('destination_property_reference')->index('htl_camp_dest_prop_idx');
                $table->string('featured_package_title');
                $table->integer('points_required_discounted');
                $table->timestamp('valid_until');
                $table->decimal('affinity_score', 5, 4)->default(0.8500);
                $table->string('interaction_status')->default('DELIVERED');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('htl_personalized_travel_campaigns');
        Schema::dropIfExists('htl_award_night_pricing_rules');
        Schema::dropIfExists('htl_alliance_transfer_logs');
        Schema::dropIfExists('htl_alliance_partners');
        Schema::dropIfExists('htl_travel_pass_point_batches');
    }
};
