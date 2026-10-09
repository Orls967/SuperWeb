<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_service_recovery_remedies', function (Blueprint $table) {
            $table->id();
            $table->string('remedy_code')->unique();
            $table->string('failure_type'); // flight_delay, bad_food, vehicle_breakdown, payment_glitch
            $table->string('customer_id');
            $table->string('remedy_type'); // goodwill_points, cash_refund, complimentary_voucher, manager_repair
            $table->decimal('remedy_value', 18, 2);
            $table->string('agent_role'); // agent, supervisor, director
            $table->boolean('within_authority_limit')->default(true); // 418.1, 418.4, 418.6
            $table->boolean('abuse_detected')->default(false); // 418.5 abuse detection
            $table->boolean('is_proactive')->default(false); // 418.2
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_service_recovery_remedies');
    }
};
