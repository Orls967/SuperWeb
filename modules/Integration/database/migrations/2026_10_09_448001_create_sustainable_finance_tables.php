<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_sustainable_financing_instruments', function (Blueprint $table) {
            $table->id();
            $table->string('instrument_code')->unique();
            $table->string('instrument_type'); // green_loan, green_sukuk, sustainability_linked (448.1)
            $table->decimal('facility_amount', 18, 2);
            $table->decimal('allocated_proceeds_amount', 18, 2)->default(0.00);
            $table->decimal('base_interest_margin_percent', 5, 2);
            $table->decimal('current_margin_adjustment_bps', 5, 2)->default(0.00); // 448.1, 448.4
            $table->boolean('reviewer_independence_verified')->default(false); // 448.3, 448.4
            $table->timestamps();
        });

        Schema::create('fin_green_proceeds_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('allocation_code')->unique();
            $table->string('instrument_code');
            $table->string('eligible_project_code');
            $table->decimal('allocated_amount', 18, 2);
            $table->boolean('is_eligible_green_project')->default(true); // 448.2, 448.5
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_green_proceeds_allocations');
        Schema::dropIfExists('fin_sustainable_financing_instruments');
    }
};
