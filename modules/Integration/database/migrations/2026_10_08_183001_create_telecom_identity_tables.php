<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 183.1 & 183.4: Federated digital identity across 30 lines
        Schema::create('tel_digital_identities', function (Blueprint $table) {
            $table->id();
            $table->string('identity_uuid')->unique();
            $table->string('user_identifier');
            $table->string('proofing_tier'); // BASIC, STAFF, VENDOR, HIGH_RISK
            $table->boolean('is_revoked')->default(false);
            $table->timestamps();
        });

        // 183.2: Verified messaging gateway (OTP & alerts with idempotent idempotency_key)
        Schema::create('tel_gateway_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key')->unique();
            $table->string('recipient_phone');
            $table->string('channel'); // SMS, WHATSAPP, EMAIL
            $table->string('message_payload');
            $table->string('delivery_status')->default('SENT'); // SENT, DELIVERED
            $table->timestamps();
        });

        // 183.3: Content network usage metering & intercompany billing
        Schema::create('tel_network_metering_bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_code')->unique();
            $table->string('tenant_code');
            $table->decimal('metered_gigabytes', 18, 2);
            $table->decimal('rate_per_gb_idr', 18, 2);
            $table->decimal('total_billed_idr', 18, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tel_network_metering_bills');
        Schema::dropIfExists('tel_gateway_notifications');
        Schema::dropIfExists('tel_digital_identities');
    }
};
