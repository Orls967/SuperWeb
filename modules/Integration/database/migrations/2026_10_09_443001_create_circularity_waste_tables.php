<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esg_waste_disposal_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_code')->unique();
            $table->string('waste_stream'); // e.g. packaging_plastic, scrap_metal, hazardous_sludge
            $table->string('hierarchy_level'); // reduce, reuse, recycle, recover, direct_dispose (443.1)
            $table->decimal('quantity_tonnes', 10, 2);
            $table->decimal('cost_per_tonne', 18, 2); // 443.6
            $table->text('hierarchy_justification')->nullable(); // 443.4, 443.5
            $table->boolean('emergency_approval_granted')->default(false); // 443.5 edge case
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->timestamps();
        });

        Schema::create('esg_waste_vendor_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_code')->unique();
            $table->string('vendor_code');
            $table->string('environmental_treatment_cert_no'); // 443.2, 443.4 treatment certificate
            $table->decimal('payment_amount', 18, 2);
            $table->boolean('treatment_certificate_verified')->default(false); // 443.2, 443.4
            $table->boolean('payment_released')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esg_waste_vendor_payments');
        Schema::dropIfExists('esg_waste_disposal_requests');
    }
};
