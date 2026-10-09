<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('min_weighbridge_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->string('ticket_number')->unique();
            $table->string('truck_plate_number');
            $table->decimal('gross_weight_ton', 8, 2);
            $table->decimal('tare_weight_ton', 8, 2);
            $table->decimal('net_weight_ton', 8, 2);
            $table->decimal('nickel_grade_percentage', 5, 2); // e.g. 1.80% Ni
            $table->string('destination_stockpile');
            $table->string('hash', 64);
            $table->string('previous_hash', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('min_royalty_calculations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->string('period', 7); // YYYY-MM
            $table->decimal('total_production_ton', 12, 2);
            $table->unsignedBigInteger('commodity_benchmark_price_idr'); // HMA per ton
            $table->decimal('royalty_rate_percentage', 5, 2); // e.g. 10.00%
            $table->unsignedBigInteger('royalty_due_idr');
            $table->string('status', 32)->default('accrued'); // accrued, paid
            $table->timestamps();

            $table->unique(['site_id', 'period']);
        });

        Schema::create('min_work_permits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->string('permit_number')->unique();
            $table->string('permit_type', 32); // BLASTING, CONFINED_SPACE, HOT_WORK
            $table->string('supervisor_name');
            $table->timestamp('valid_until');
            $table->string('status', 32)->default('active'); // active, expired, revoked
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('min_work_permits');
        Schema::dropIfExists('min_royalty_calculations');
        Schema::dropIfExists('min_weighbridge_tickets');
    }
};
