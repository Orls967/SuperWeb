<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        gc_collect_cycles();

        Schema::table('htl_rate_plans', function (Blueprint $table) {
            $table->unsignedInteger('min_stay')->nullable()->after('penalty_nights');
            $table->unsignedInteger('max_stay')->nullable()->after('min_stay');
        });

        Schema::table('htl_rate_calendar', function (Blueprint $table) {
            $table->boolean('closed_to_arrival')->default(false)->after('rate_idr');
            $table->boolean('stop_sell')->default(false)->after('closed_to_arrival');
        });

        Schema::table('htl_folios', function (Blueprint $table) {
            $table->unsignedBigInteger('credit_limit_idr')->nullable()->after('outstanding_balance');
        });

        Schema::table('htl_properties', function (Blueprint $table) {
            $table->string('check_out_time', 8)->default('12:00:00')->after('tax_pbjt_bps');
            $table->unsignedBigInteger('late_checkout_fee_idr')->default(0)->after('check_out_time');
        });

        Schema::table('htl_deposits', function (Blueprint $table) {
            $table->string('refund_idempotency_key', 128)->nullable()->after('idempotency_key');
        });

        gc_collect_cycles();
    }

    public function down(): void
    {
        Schema::table('htl_deposits', function (Blueprint $table) {
            $table->dropColumn('refund_idempotency_key');
        });

        Schema::table('htl_properties', function (Blueprint $table) {
            $table->dropColumn(['check_out_time', 'late_checkout_fee_idr']);
        });

        Schema::table('htl_folios', function (Blueprint $table) {
            $table->dropColumn('credit_limit_idr');
        });

        Schema::table('htl_rate_calendar', function (Blueprint $table) {
            $table->dropColumn(['closed_to_arrival', 'stop_sell']);
        });

        Schema::table('htl_rate_plans', function (Blueprint $table) {
            $table->dropColumn(['min_stay', 'max_stay']);
        });
    }
};
