<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgx_carriers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 24)->unique();
            $table->string('name');
            $table->string('mode', 16)->nullable(); // road, sea, air
            $table->unsignedSmallInteger('payment_terms_days')->default(7);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('lgx_carrier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrier_id')->constrained('lgx_carriers')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_idr');
            $table->unsignedInteger('leg_count');
            $table->string('week_key', 10); // 2026-W40
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index(['carrier_id', 'week_key']);
        });

        Schema::table('lgx_shipment_legs', function (Blueprint $table) {
            $table->foreignId('carrier_id')->nullable()->after('schedule_id')->constrained('lgx_carriers')->nullOnDelete();
            $table->unsignedBigInteger('carrier_cost_idr')->default(0)->after('carrier_id');
            $table->timestamp('cost_accrued_at')->nullable()->after('carrier_cost_idr');
            $table->foreignId('carrier_payment_id')->nullable()->after('cost_accrued_at')->constrained('lgx_carrier_payments')->nullOnDelete();

            $table->index(['carrier_id', 'cost_accrued_at']);
        });
    }

    public function down(): void
    {
        Schema::table('lgx_shipment_legs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('carrier_payment_id');
            $table->dropConstrainedForeignId('carrier_id');
            $table->dropColumn(['carrier_cost_idr', 'cost_accrued_at']);
        });
        Schema::dropIfExists('lgx_carrier_payments');
        Schema::dropIfExists('lgx_carriers');
    }
};
