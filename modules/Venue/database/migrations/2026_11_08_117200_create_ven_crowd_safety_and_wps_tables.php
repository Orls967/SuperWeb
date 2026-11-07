<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Perluas ven_biometric_credentials dengan atribut privasi & liveness
        if (Schema::hasTable('ven_biometric_credentials')) {
            Schema::table('ven_biometric_credentials', function (Blueprint $table) {
                if (! Schema::hasColumn('ven_biometric_credentials', 'liveness_score_bps')) {
                    $table->unsignedInteger('liveness_score_bps')->default(9000)->after('liveness_signature_hash');
                }
                if (! Schema::hasColumn('ven_biometric_credentials', 'sensor_model')) {
                    $table->string('sensor_model', 64)->default('FACECAM-3D-PRO')->after('liveness_score_bps');
                }
                if (! Schema::hasColumn('ven_biometric_credentials', 'status')) {
                    $table->string('status', 32)->default('ENROLLED_ACTIVE')->after('sensor_model');
                }
                if (! Schema::hasColumn('ven_biometric_credentials', 'consented_at')) {
                    $table->timestamp('consented_at')->nullable()->after('status');
                }
                if (! Schema::hasColumn('ven_biometric_credentials', 'revoked_at')) {
                    $table->timestamp('revoked_at')->nullable()->after('consented_at');
                }
            });
        }

        // 1b. Perluas ven_crowd_crush_incidents dengan resolved_at
        if (Schema::hasTable('ven_crowd_crush_incidents')) {
            Schema::table('ven_crowd_crush_incidents', function (Blueprint $table) {
                if (! Schema::hasColumn('ven_crowd_crush_incidents', 'resolved_at')) {
                    $table->timestamp('resolved_at')->nullable()->after('status');
                }
            });
        }

        // 2. Perluas ven_personalized_promos dengan floor guardrail & tracking
        if (Schema::hasTable('ven_personalized_promos')) {
            Schema::table('ven_personalized_promos', function (Blueprint $table) {
                if (! Schema::hasColumn('ven_personalized_promos', 'discount_bps')) {
                    $table->unsignedInteger('discount_bps')->default(1000)->after('discount_percent');
                }
                if (! Schema::hasColumn('ven_personalized_promos', 'floor_price_idr')) {
                    $table->unsignedBigInteger('floor_price_idr')->default(0)->after('discount_bps');
                }
                if (! Schema::hasColumn('ven_personalized_promos', 'delivered_at')) {
                    $table->timestamp('delivered_at')->nullable()->after('status');
                }
                if (! Schema::hasColumn('ven_personalized_promos', 'redeemed_at')) {
                    $table->timestamp('redeemed_at')->nullable()->after('delivered_at');
                }
            });
        }

        // 3. Tabel Metrik Waktu Tunggu Layanan Bar & F&B (WPS)
        if (! Schema::hasTable('ven_zone_wps_metrics')) {
            Schema::create('ven_zone_wps_metrics', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id')->nullable();
                $table->unsignedBigInteger('zone_id');
                $table->unsignedInteger('station_number')->default(1);
                $table->unsignedInteger('active_servers_count')->default(2);
                $table->unsignedInteger('arrival_rate_bps')->default(3000);
                $table->unsignedInteger('service_rate_bps')->default(2000);
                $table->unsignedInteger('utilization_bps')->default(7500);
                $table->unsignedInteger('average_wait_minutes')->default(3);
                $table->unsignedInteger('wps_score')->default(80);
                $table->boolean('reinforcement_needed')->default(false);
                $table->unsignedInteger('recommended_additional_servers')->default(0);
                $table->timestamp('recorded_at')->nullable();
                $table->timestamps();

                $table->foreign('event_id')->references('id')->on('ven_entertainment_events')->cascadeOnDelete();
                $table->foreign('zone_id')->references('id')->on('ven_entertainment_zones')->cascadeOnDelete();
            });
        }

        // 4. Tabel Gerbang Turnstile & Jalur Evakuasi Sekunder
        if (! Schema::hasTable('ven_gate_turnstiles')) {
            Schema::create('ven_gate_turnstiles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('venue_id');
                $table->unsignedBigInteger('zone_id');
                $table->string('gate_code', 32)->unique();
                $table->string('gate_name', 64);
                $table->string('turnstile_type', 32)->default('BIOMETRIC_TRIPOD'); // BIOMETRIC_TRIPOD, FLAP_BARRIER, MANUAL_SCAN
                $table->boolean('is_emergency_exit')->default(false);
                $table->boolean('is_locked')->default(false);
                $table->unsignedInteger('total_scans_in')->default(0);
                $table->unsignedInteger('total_scans_out')->default(0);
                $table->timestamp('last_scan_at')->nullable();
                $table->timestamps();

                $table->foreign('venue_id')->references('id')->on('ven_entertainment_venues')->cascadeOnDelete();
                $table->foreign('zone_id')->references('id')->on('ven_entertainment_zones')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ven_gate_turnstiles');
        Schema::dropIfExists('ven_zone_wps_metrics');
    }
};
