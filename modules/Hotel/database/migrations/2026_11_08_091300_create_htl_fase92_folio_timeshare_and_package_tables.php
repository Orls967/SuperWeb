<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Akun Korporat & Direct Billing
        if (! Schema::hasTable('htl_corporate_accounts')) {
            Schema::create('htl_corporate_accounts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('company_name', 120);
                $table->string('tax_id_npwp', 40)->nullable();
                $table->string('billing_email', 100);
                $table->bigInteger('credit_limit_idr')->default(10000000);
                $table->bigInteger('current_outstanding_idr')->default(0);
                $table->string('status', 30)->default('ACTIVE');
                $table->timestamps();
            });
        }

        // 2. Sub-Folio Multi-Payer
        if (! Schema::hasTable('htl_sub_folios')) {
            Schema::create('htl_sub_folios', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('folio_id');
                $table->string('payer_type', 30); // PERSONAL, CORPORATE
                $table->uuid('corporate_id')->nullable();
                $table->bigInteger('total_charges_idr')->default(0);
                $table->bigInteger('total_payments_idr')->default(0);
                $table->bigInteger('balance_idr')->default(0);
                $table->string('status', 30)->default('OPEN');
                $table->timestamps();

                $table->foreign('folio_id')->references('id')->on('htl_folios')->cascadeOnDelete();
                $table->foreign('corporate_id')->references('id')->on('htl_corporate_accounts')->nullOnDelete();
            });
        }

        // 3. Timeshare Schedules
        if (! Schema::hasTable('htl_timeshare_schedules')) {
            Schema::create('htl_timeshare_schedules', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('timeshare_unit_id');
                $table->string('owner_reference', 64);
                $table->integer('week_number');
                $table->string('season', 30);
                $table->string('status', 30)->default('OWNER_OCCUPIED'); // OWNER_OCCUPIED, RENTAL_POOL, LEASED
                $table->bigInteger('rental_income_gross_idr')->default(0);
                $table->bigInteger('dividend_net_idr')->default(0);
                $table->timestamps();

                $table->foreign('timeshare_unit_id')->references('id')->on('htl_timeshare_units')->cascadeOnDelete();
            });
        }

        // 4. Destination Package Bookings & Allocations
        if (! Schema::hasTable('htl_destination_package_bookings')) {
            Schema::create('htl_destination_package_bookings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('destination_package_id');
                $table->string('guest_reference', 64);
                $table->bigInteger('total_paid_idr');
                $table->bigInteger('escrow_held_idr');
                $table->string('escrow_status', 30)->default('HELD');
                $table->timestamps();

                $table->foreign('destination_package_id')->references('id')->on('htl_destination_packages')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('htl_destination_package_allocations')) {
            Schema::create('htl_destination_package_allocations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('package_booking_id');
                $table->string('vendor_type', 40); // HOTEL, VENUE, LOGISTICS, RESTO
                $table->string('vendor_reference', 64);
                $table->bigInteger('ssp_amount_idr');
                $table->string('release_status', 30)->default('PENDING'); // PENDING, RELEASED, REFUNDED
                $table->timestamp('released_at')->nullable();
                $table->timestamps();

                $table->foreign('package_booking_id')->references('id')->on('htl_destination_package_bookings')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('htl_destination_package_allocations');
        Schema::dropIfExists('htl_destination_package_bookings');
        Schema::dropIfExists('htl_timeshare_schedules');
        Schema::dropIfExists('htl_sub_folios');
        Schema::dropIfExists('htl_corporate_accounts');
    }
};
