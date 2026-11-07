<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Parameter Tarif Royalti Berversi (PP 19/2025)
        if (! Schema::hasTable('min_royalty_rate_versions')) {
            Schema::create('min_royalty_rate_versions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('version_tag', 32)->default('PP19_2025_V1');
                $table->unsignedInteger('version')->default(1);
                $table->string('commodity', 32);
                $table->string('product_type', 32);
                $table->string('mining_method', 32)->default('OPEN_PIT');
                $table->unsignedInteger('calorific_min_kcal')->default(0);
                $table->unsignedInteger('calorific_max_kcal')->default(0);
                $table->unsignedInteger('price_tier_min_usd_cents')->default(0);
                $table->unsignedInteger('price_tier_max_usd_cents')->default(0);
                $table->unsignedInteger('rate_bps');
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->string('source', 120)->default('PP 19/2025');
                $table->string('label', 32)->default('regulasi');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['commodity', 'mining_method', 'effective_from'], 'min_rrv_comm_meth_eff_idx');
            });
        }

        // 2. Deret Harga Acuan Komoditas (HBA, HMA, HPB)
        if (! Schema::hasTable('min_reference_prices')) {
            Schema::create('min_reference_prices', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('commodity', 32);
                $table->string('price_type', 16); // HBA, HMA, HPB
                $table->date('period_date');
                $table->unsignedSmallInteger('period_phase')->default(1);
                $table->unsignedBigInteger('price_cents');
                $table->string('currency', 3)->default('USD');
                $table->string('source', 120);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->index(['commodity', 'price_type', 'period_date'], 'min_ref_comm_type_date_idx');
            });
        }

        // 3. Rekonsiliasi Neraca Bijih, Stockpile & Pengapalan (M5)
        if (! Schema::hasTable('min_reconciliation_runs')) {
            Schema::create('min_reconciliation_runs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('run_number', 40)->unique();
                $table->foreignUuid('site_id')->constrained('min_sites')->cascadeOnDelete();
                $table->string('period_month', 7);
                $table->string('status', 24)->default('open');
                $table->unsignedBigInteger('total_ore_mined_tons')->default(0);
                $table->unsignedBigInteger('total_stockpile_tons')->default(0);
                $table->unsignedBigInteger('total_shipped_tons')->default(0);
                $table->bigInteger('variance_tons')->default(0);
                $table->bigInteger('variance_bps')->default(0);
                $table->text('investigation_notes')->nullable();
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->timestamps();

                $table->index(['site_id', 'period_month']);
                $table->index('status');
            });
        } else {
            Schema::table('min_reconciliation_runs', function (Blueprint $table) {
                if (! Schema::hasColumn('min_reconciliation_runs', 'run_number')) {
                    $table->string('run_number', 40)->nullable()->unique();
                }
                if (! Schema::hasColumn('min_reconciliation_runs', 'period_month')) {
                    $table->string('period_month', 7)->nullable();
                }
                if (! Schema::hasColumn('min_reconciliation_runs', 'total_ore_mined_tons')) {
                    $table->unsignedBigInteger('total_ore_mined_tons')->default(0);
                }
                if (! Schema::hasColumn('min_reconciliation_runs', 'total_stockpile_tons')) {
                    $table->unsignedBigInteger('total_stockpile_tons')->default(0);
                }
                if (! Schema::hasColumn('min_reconciliation_runs', 'total_shipped_tons')) {
                    $table->unsignedBigInteger('total_shipped_tons')->default(0);
                }
                if (! Schema::hasColumn('min_reconciliation_runs', 'variance_tons')) {
                    $table->bigInteger('variance_tons')->default(0);
                }
                if (! Schema::hasColumn('min_reconciliation_runs', 'investigation_notes')) {
                    $table->text('investigation_notes')->nullable();
                }
                if (! Schema::hasColumn('min_reconciliation_runs', 'approved_by_user_id')) {
                    $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('min_reconciliation_runs', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable();
                }
                if (! Schema::hasColumn('min_reconciliation_runs', 'locked_at')) {
                    $table->timestamp('locked_at')->nullable();
                }
            });
        }

        // 4. Perbandingan Assay & Umpire Lab
        if (! Schema::hasTable('min_assay_comparisons')) {
            Schema::create('min_assay_comparisons', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('lot_number', 40)->unique();
                $table->foreignUuid('site_id')->constrained('min_sites')->cascadeOnDelete();
                $table->unsignedInteger('lab_assay_ni_ppm');
                $table->unsignedInteger('lab_assay_fe_ppm');
                $table->unsignedInteger('lab_assay_moisture_permil');
                $table->unsignedInteger('umpire_assay_ni_ppm');
                $table->unsignedInteger('umpire_assay_fe_ppm');
                $table->unsignedInteger('umpire_assay_moisture_permil');
                $table->integer('bias_ni_ppm');
                $table->boolean('is_disputed')->default(false);
                $table->string('dispute_status', 24)->default('settled');
                $table->timestamp('dispute_resolved_at')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->timestamps();

                $table->index(['site_id', 'is_disputed']);
            });
        }

        // 5. Perizinan Konsesi & IUP
        if (! Schema::hasTable('min_concession_permits')) {
            Schema::create('min_concession_permits', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('permit_number', 60)->unique();
                $table->foreignUuid('site_id')->constrained('min_sites')->cascadeOnDelete();
                $table->string('permit_type', 32); // IUP, IUPK, IPPKH, AMDAL
                $table->unsignedInteger('concession_area_hectares');
                $table->date('issued_date');
                $table->date('expiry_date');
                $table->string('status', 24)->default('active');
                $table->string('renewal_application_ref', 60)->nullable();
                $table->timestamps();

                $table->index(['site_id', 'expiry_date']);
                $table->index('status');
            });
        }

        // 6. Sensor Lingkungan Tambang & HSE
        if (! Schema::hasTable('min_environmental_readings')) {
            Schema::create('min_environmental_readings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('site_id')->constrained('min_sites')->cascadeOnDelete();
                $table->string('location_code', 40);
                $table->string('sensor_type', 32); // dust_pm10, noise_db, vibration_pvs, water_ph
                $table->unsignedInteger('reading_value');
                $table->string('unit_of_measure', 16);
                $table->unsignedInteger('threshold_limit');
                $table->boolean('is_breached')->default(false);
                $table->boolean('area_shutdown_triggered')->default(false);
                $table->timestamp('recorded_at');
                $table->timestamps();

                $table->index(['site_id', 'sensor_type', 'recorded_at'], 'min_env_site_sensor_idx');
            });
        }

        // 7. Jadwal Provisi & Reklamasi Pascatambang
        if (! Schema::hasTable('min_reclamation_schedules')) {
            Schema::create('min_reclamation_schedules', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('project_number', 40)->unique();
                $table->foreignUuid('site_id')->constrained('min_sites')->cascadeOnDelete();
                $table->string('phase_name', 60);
                $table->unsignedSmallInteger('planned_year');
                $table->unsignedTinyInteger('planned_quarter');
                $table->unsignedInteger('target_area_hectares');
                $table->unsignedInteger('completed_area_hectares')->default(0);
                $table->unsignedBigInteger('provision_amount_minor');
                $table->unsignedBigInteger('unwound_amount_minor')->default(0);
                $table->unsignedBigInteger('guarantee_bond_amount_minor')->default(0);
                $table->string('status', 24)->default('scheduled');
                $table->timestamps();

                $table->index(['site_id', 'planned_year', 'planned_quarter'], 'min_recl_site_time_idx');
            });
        }

        // 8. Kontrak Offtake Komoditas
        if (! Schema::hasTable('min_offtake_contracts')) {
            Schema::create('min_offtake_contracts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('site_id')->nullable()->constrained('min_sites')->nullOnDelete();
                $table->string('contract_number', 40)->unique();
                $table->string('contract_code', 40)->nullable()->index();
                $table->string('buyer_party_ref', 80)->nullable()->index();
                $table->unsignedBigInteger('party_ref')->default(1);
                $table->string('commodity', 32);
                $table->string('destination_type', 16)->default('DOMESTIC');
                $table->string('pricing_formula', 60)->default('INDEX_ADJUSTED');
                $table->unsignedBigInteger('base_price_cents')->default(0);
                $table->unsignedBigInteger('base_price_usd_cents')->default(0);
                $table->unsignedBigInteger('target_volume_tons')->default(0);
                $table->unsignedBigInteger('contracted_tonnage_kg')->default(0);
                $table->unsignedBigInteger('fulfilled_volume_tons')->default(0);
                $table->unsignedInteger('benchmark_gar_kcal')->default(5000);
                $table->boolean('lc_required')->default(true);
                $table->string('status', 24)->default('draft');
                $table->timestamps();

                $table->index(['party_ref', 'status']);
            });
        }

        // 9. Pengapalan Offtake & Saga Status (10 status)
        if (! Schema::hasTable('min_offtake_shipments')) {
            Schema::create('min_offtake_shipments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('shipment_number', 40)->unique();
                $table->foreignUuid('contract_id')->constrained('min_offtake_contracts')->cascadeOnDelete();
                $table->unsignedBigInteger('party_ref');
                $table->string('vessel_name', 80);
                $table->date('laycan_start');
                $table->date('laycan_end');
                $table->unsignedBigInteger('nominated_volume_tons');
                $table->unsignedBigInteger('loaded_volume_tons')->default(0);
                $table->string('lc_ref', 60)->nullable();
                $table->string('status', 24)->default('contracted');
                $table->unsignedBigInteger('provisional_invoice_ref')->nullable();
                $table->unsignedBigInteger('final_invoice_ref')->nullable();
                $table->boolean('is_export')->default(false);
                $table->boolean('export_prohibited_diverted')->default(false);
                $table->string('diversion_reason', 120)->nullable();
                $table->timestamps();

                $table->index(['contract_id', 'status']);
                $table->index('status');
            });
        }

        // 10. Tambahan kolom pada min_work_permits jika sudah ada, atau buat baru jika belum
        if (Schema::hasTable('min_work_permits')) {
            Schema::table('min_work_permits', function (Blueprint $table) {
                if (! Schema::hasColumn('min_work_permits', 'worker_id')) {
                    $table->string('worker_id', 40)->nullable();
                }
                if (! Schema::hasColumn('min_work_permits', 'activity_type')) {
                    $table->string('activity_type', 32)->nullable();
                }
                if (! Schema::hasColumn('min_work_permits', 'valid_from')) {
                    $table->timestamp('valid_from')->nullable();
                }
                if (! Schema::hasColumn('min_work_permits', 'geofence_zone')) {
                    $table->string('geofence_zone', 40)->nullable();
                }
                if (! Schema::hasColumn('min_work_permits', 'risk_level')) {
                    $table->string('risk_level', 16)->default('medium');
                }
                if (! Schema::hasColumn('min_work_permits', 'pit_id')) {
                    $table->foreignUuid('pit_id')->nullable()->constrained('min_pits')->nullOnDelete();
                }
                if (! Schema::hasColumn('min_work_permits', 'approved_by_user_id')) {
                    $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('min_work_permits', 'capa_ref')) {
                    $table->string('capa_ref', 60)->nullable();
                }
            });
        }

        // 11. SPT Royalti Bulanan (Royalty Return - 8 status)
        if (! Schema::hasTable('min_royalty_returns')) {
            Schema::create('min_royalty_returns', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('return_number', 40)->unique();
                $table->foreignUuid('site_id')->constrained('min_sites')->cascadeOnDelete();
                $table->string('period_month', 7);
                $table->unsignedBigInteger('verified_volume_tons');
                $table->unsignedBigInteger('benchmark_price_cents');
                $table->unsignedInteger('exchange_rate_idr');
                $table->unsignedBigInteger('calculated_royalty_usd_cents');
                $table->unsignedBigInteger('calculated_royalty_idr');
                $table->unsignedInteger('rate_bps')->default(0);
                $table->string('status', 24)->default('draft');
                $table->string('document_number', 40)->nullable();
                $table->string('payment_ref', 60)->nullable();
                $table->timestamps();

                $table->index(['site_id', 'period_month']);
                $table->index('status');
            });
        } else {
            Schema::table('min_royalty_returns', function (Blueprint $table) {
                if (! Schema::hasColumn('min_royalty_returns', 'return_number')) {
                    $table->string('return_number', 40)->nullable()->unique();
                }
                if (! Schema::hasColumn('min_royalty_returns', 'period_month')) {
                    $table->string('period_month', 7)->nullable();
                }
                if (! Schema::hasColumn('min_royalty_returns', 'verified_volume_tons')) {
                    $table->unsignedBigInteger('verified_volume_tons')->default(0);
                }
                if (! Schema::hasColumn('min_royalty_returns', 'benchmark_price_cents')) {
                    $table->unsignedBigInteger('benchmark_price_cents')->default(0);
                }
                if (! Schema::hasColumn('min_royalty_returns', 'exchange_rate_idr')) {
                    $table->unsignedInteger('exchange_rate_idr')->default(16000);
                }
                if (! Schema::hasColumn('min_royalty_returns', 'calculated_royalty_usd_cents')) {
                    $table->unsignedBigInteger('calculated_royalty_usd_cents')->default(0);
                }
                if (! Schema::hasColumn('min_royalty_returns', 'rate_bps')) {
                    $table->unsignedInteger('rate_bps')->default(0);
                }
                if (! Schema::hasColumn('min_royalty_returns', 'document_number')) {
                    $table->string('document_number', 40)->nullable();
                }
                if (! Schema::hasColumn('min_royalty_returns', 'payment_ref')) {
                    $table->string('payment_ref', 60)->nullable();
                }
            });
        }

        // 12. Tambahan kolom pada min_stockpiles untuk rekonsiliasi
        if (Schema::hasTable('min_stockpiles')) {
            Schema::table('min_stockpiles', function (Blueprint $table) {
                if (! Schema::hasColumn('min_stockpiles', 'avg_grade_pct')) {
                    $table->decimal('avg_grade_pct', 5, 2)->default(0);
                }
            });
        }

        // 13. Tambahan kolom pada min_weighbridge_tickets untuk hash-chain tiket timbang
        if (Schema::hasTable('min_weighbridge_tickets')) {
            Schema::table('min_weighbridge_tickets', function (Blueprint $table) {
                if (! Schema::hasColumn('min_weighbridge_tickets', 'stockpile_id')) {
                    $table->foreignUuid('stockpile_id')->nullable()->constrained('min_stockpiles')->nullOnDelete();
                }
                if (! Schema::hasColumn('min_weighbridge_tickets', 'vehicle_plate')) {
                    $table->string('vehicle_plate', 24)->nullable();
                }
                if (! Schema::hasColumn('min_weighbridge_tickets', 'gross_weight_kg')) {
                    $table->unsignedBigInteger('gross_weight_kg')->default(0);
                }
                if (! Schema::hasColumn('min_weighbridge_tickets', 'tare_weight_kg')) {
                    $table->unsignedBigInteger('tare_weight_kg')->default(0);
                }
                if (! Schema::hasColumn('min_weighbridge_tickets', 'net_weight_kg')) {
                    $table->unsignedBigInteger('net_weight_kg')->default(0);
                }
                if (! Schema::hasColumn('min_weighbridge_tickets', 'current_hash')) {
                    $table->string('current_hash', 64)->nullable();
                }
                if (! Schema::hasColumn('min_weighbridge_tickets', 'weighed_at')) {
                    $table->timestamp('weighed_at')->nullable();
                }
                if (! Schema::hasColumn('min_weighbridge_tickets', 'destination')) {
                    $table->string('destination', 32)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('min_royalty_returns');
        Schema::dropIfExists('min_offtake_shipments');
        Schema::dropIfExists('min_offtake_contracts');
        Schema::dropIfExists('min_reclamation_schedules');
        Schema::dropIfExists('min_environmental_readings');
        Schema::dropIfExists('min_concession_permits');
        Schema::dropIfExists('min_assay_comparisons');
        Schema::dropIfExists('min_reconciliation_runs');
        Schema::dropIfExists('min_reference_prices');
        Schema::dropIfExists('min_royalty_rate_versions');
    }
};
