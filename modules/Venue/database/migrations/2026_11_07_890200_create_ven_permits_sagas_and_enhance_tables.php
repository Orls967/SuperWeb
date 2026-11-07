<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ven_permits')) {
            Schema::create('ven_permits', function (Blueprint $table) {
                $table->id();
                $table->string('permit_number', 64)->unique();
                $table->unsignedBigInteger('event_id');
                $table->string('issuing_authority', 128);
                $table->integer('max_legal_capacity');
                $table->timestamp('valid_from');
                $table->timestamp('valid_until');
                $table->string('status', 32)->default('PENDING'); // PENDING, APPROVED, REVOKED, EXPIRED
                $table->string('revocation_reason', 255)->nullable();
                $table->timestamps();

                $table->foreign('event_id')->references('id')->on('ven_entertainment_events')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('ven_sagas')) {
            Schema::create('ven_sagas', function (Blueprint $table) {
                $table->id();
                $table->string('saga_type', 64);
                $table->string('saga_reference', 64)->unique();
                $table->string('current_step', 64);
                $table->string('status', 32)->default('STARTED');
                $table->json('payload_json')->nullable();
                $table->json('compensation_log_json')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ven_external_factors')) {
            Schema::create('ven_external_factors', function (Blueprint $table) {
                $table->id();
                $table->string('factor_key', 64);
                $table->integer('version')->default(1);
                $table->date('effective_from');
                $table->string('source', 128);
                $table->string('label', 32)->default('asumsi_simulasi');
                $table->bigInteger('numeric_value_scaled');
                $table->string('unit', 32);
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->unique(['factor_key', 'version']);
            });
        }

        Schema::table('ven_entertainment_events', function (Blueprint $table) {
            if (! Schema::hasColumn('ven_entertainment_events', 'doors_closed_at')) {
                $table->dateTime('doors_closed_at')->nullable()->after('doors_open_at');
            }
            if (! Schema::hasColumn('ven_entertainment_events', 'settled_at')) {
                $table->timestamp('settled_at')->nullable()->after('status');
            }
        });

        Schema::table('ven_entertainment_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('ven_entertainment_tickets', 'tax_paid_idr')) {
                $table->bigInteger('tax_paid_idr')->default(0)->after('price_paid_idr');
            }
            if (! Schema::hasColumn('ven_entertainment_tickets', 'reentry_count')) {
                $table->integer('reentry_count')->default(0)->after('status');
            }
            if (! Schema::hasColumn('ven_entertainment_tickets', 'last_direction')) {
                $table->string('last_direction', 8)->nullable()->after('reentry_count');
            }
        });

        Schema::table('ven_entertainment_table_bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('ven_entertainment_table_bookings', 'actual_spend_idr')) {
                $table->bigInteger('actual_spend_idr')->default(0)->after('deposit_amount_idr');
            }
            if (! Schema::hasColumn('ven_entertainment_table_bookings', 'shortfall_idr')) {
                $table->bigInteger('shortfall_idr')->default(0)->after('actual_spend_idr');
            }
            if (! Schema::hasColumn('ven_entertainment_table_bookings', 'forfeited_deposit_idr')) {
                $table->bigInteger('forfeited_deposit_idr')->default(0)->after('shortfall_idr');
            }
        });

        Schema::table('ven_gate_scans', function (Blueprint $table) {
            if (! Schema::hasColumn('ven_gate_scans', 'nonce')) {
                $table->string('nonce', 64)->nullable()->after('scan_result');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ven_external_factors');
        Schema::dropIfExists('ven_sagas');
        Schema::dropIfExists('ven_permits');
    }
};

