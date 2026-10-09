<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 55.2 Mesin Webhook B2B & HMAC Signature
        Schema::create('intg_webhook_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('partner_id', 32);
            $table->string('event_type', 64); // order.paid, shipment.delivered, po.issued
            $table->string('target_url');
            $table->string('secret_key', 64);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('intg_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('intg_webhook_subscriptions')->cascadeOnDelete();
            $table->string('event_id', 32);
            $table->text('payload');
            $table->string('signature', 64);
            $table->integer('http_status')->default(200);
            $table->string('delivery_status', 20)->default('delivered'); // delivered, failed, dlq
            $table->integer('attempt_count')->default(1);
            $table->timestamps();
        });

        // 55.3 B2B Electronic Data Interchange (EDI 850/855/856/810)
        Schema::create('intg_edi_messages', function (Blueprint $table) {
            $table->id();
            $table->string('control_number', 32)->unique();
            $table->string('edi_standard', 10)->default('X12'); // X12, EDIFACT
            $table->string('transaction_set', 10); // 850, 855, 856, 810, 997
            $table->string('sender_id', 32);
            $table->string('receiver_id', 32);
            $table->text('raw_message');
            $table->string('functional_status', 20)->default('accepted'); // accepted, rejected, ack_received
            $table->timestamps();
        });

        // 55.5 B2B Client API Keys & Tiered Rate Limiting
        Schema::create('intg_api_clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_id', 32)->unique();
            $table->string('client_name');
            $table->string('api_key_hash', 64)->unique();
            $table->string('tier', 20)->default('GOLD'); // SILVER, GOLD, PLATINUM
            $table->integer('rate_limit_per_minute')->default(600);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intg_api_clients');
        Schema::dropIfExists('intg_edi_messages');
        Schema::dropIfExists('intg_webhook_deliveries');
        Schema::dropIfExists('intg_webhook_subscriptions');
    }
};
