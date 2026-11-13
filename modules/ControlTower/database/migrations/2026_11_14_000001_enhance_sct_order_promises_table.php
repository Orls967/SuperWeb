<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sct_order_promises', function (Blueprint $table): void {
            if (! Schema::hasColumn('sct_order_promises', 'idempotency_key')) {
                $table->string('idempotency_key', 120)->nullable()->unique()->after('promise_status');
            }
            if (! Schema::hasColumn('sct_order_promises', 'payload_hash')) {
                $table->string('payload_hash', 64)->nullable()->after('idempotency_key');
            }
            if (! Schema::hasColumn('sct_order_promises', 'promised_period')) {
                $table->integer('promised_period')->nullable()->after('payload_hash');
            }
            if (! Schema::hasColumn('sct_order_promises', 'late_periods')) {
                $table->integer('late_periods')->nullable()->after('promised_period');
            }
            if (! Schema::hasColumn('sct_order_promises', 'reason_code')) {
                $table->string('reason_code', 48)->nullable()->after('late_periods');
            }
            if (! Schema::hasColumn('sct_order_promises', 'certificate_hash')) {
                $table->string('certificate_hash', 64)->nullable()->after('reason_code');
            }
            if (! Schema::hasColumn('sct_order_promises', 'as_of_date')) {
                $table->date('as_of_date')->nullable()->after('certificate_hash');
            }
            if (! Schema::hasColumn('sct_order_promises', 'atp_cumulative')) {
                $table->integer('atp_cumulative')->nullable()->after('as_of_date');
            }
            if (! Schema::hasColumn('sct_order_promises', 'ctp_cumulative')) {
                $table->integer('ctp_cumulative')->nullable()->after('atp_cumulative');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sct_order_promises', function (Blueprint $table): void {
            if (Schema::hasColumn('sct_order_promises', 'idempotency_key')) {
                $table->dropUnique(['idempotency_key']);
            }
            $columns = array_values(array_filter([
                'idempotency_key',
                'payload_hash',
                'promised_period',
                'late_periods',
                'reason_code',
                'certificate_hash',
                'as_of_date',
                'atp_cumulative',
                'ctp_cumulative',
            ], static fn (string $col): bool => Schema::hasColumn('sct_order_promises', $col)));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
