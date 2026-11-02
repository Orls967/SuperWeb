<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('htl_rate_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('htl_rate_plans', 'min_stay')) {
                $table->unsignedInteger('min_stay')->nullable()->after('penalty_nights');
            }
            if (! Schema::hasColumn('htl_rate_plans', 'max_stay')) {
                $table->unsignedInteger('max_stay')->nullable()->after('min_stay');
            }
        });

        Schema::table('htl_rate_calendar', function (Blueprint $table) {
            if (! Schema::hasColumn('htl_rate_calendar', 'closed_to_arrival')) {
                $table->boolean('closed_to_arrival')->default(false)->after('rate_idr');
            }
            if (! Schema::hasColumn('htl_rate_calendar', 'stop_sell')) {
                $table->boolean('stop_sell')->default(false)->after('closed_to_arrival');
            }
        });

        Schema::table('htl_folios', function (Blueprint $table) {
            if (! Schema::hasColumn('htl_folios', 'credit_limit_idr')) {
                $table->unsignedBigInteger('credit_limit_idr')->nullable()->after('outstanding_balance');
            }
        });

        Schema::table('htl_properties', function (Blueprint $table) {
            if (! Schema::hasColumn('htl_properties', 'check_out_time')) {
                $table->string('check_out_time', 8)->default('12:00:00')->after('tax_pbjt_bps');
            }
            if (! Schema::hasColumn('htl_properties', 'late_checkout_fee_idr')) {
                $table->unsignedBigInteger('late_checkout_fee_idr')->default(0)->after('check_out_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('htl_properties', function (Blueprint $table) {
            if (Schema::hasColumn('htl_properties', 'late_checkout_fee_idr')) {
                $table->dropColumn('late_checkout_fee_idr');
            }
            if (Schema::hasColumn('htl_properties', 'check_out_time')) {
                $table->dropColumn('check_out_time');
            }
        });

        Schema::table('htl_folios', function (Blueprint $table) {
            if (Schema::hasColumn('htl_folios', 'credit_limit_idr')) {
                $table->dropColumn('credit_limit_idr');
            }
        });

        Schema::table('htl_rate_calendar', function (Blueprint $table) {
            if (Schema::hasColumn('htl_rate_calendar', 'stop_sell')) {
                $table->dropColumn('stop_sell');
            }
            if (Schema::hasColumn('htl_rate_calendar', 'closed_to_arrival')) {
                $table->dropColumn('closed_to_arrival');
            }
        });

        Schema::table('htl_rate_plans', function (Blueprint $table) {
            if (Schema::hasColumn('htl_rate_plans', 'max_stay')) {
                $table->dropColumn('max_stay');
            }
            if (Schema::hasColumn('htl_rate_plans', 'min_stay')) {
                $table->dropColumn('min_stay');
            }
        });
    }
};
