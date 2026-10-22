<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('core_partner_webhooks')) {
            Schema::create('core_partner_webhooks', function (Blueprint $table): void {
                $table->id();
                $table->string('tenant_id', 64)->default('tenant-default');
                $table->string('partner_name', 128);
                $table->string('endpoint_url', 255);
                $table->string('hmac_secret', 128);
                $table->string('event_subscriptions', 255)->default('*');
                $table->integer('retry_limit')->default(5);
                $table->integer('timeout_seconds')->default(10);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'is_active']);
                $table->index(['partner_name', 'is_active']);
            });
        }

        if (! Schema::hasTable('core_webhook_deliveries')) {
            Schema::create('core_webhook_deliveries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('webhook_id')->constrained('core_partner_webhooks')->cascadeOnDelete();
                $table->string('event_name', 64);
                $table->text('payload');
                $table->integer('attempt')->default(1);
                $table->integer('http_status')->nullable();
                $table->string('status', 32)->default('PENDING');
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();

                $table->index(['webhook_id', 'status', 'delivered_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('core_webhook_deliveries');
        Schema::dropIfExists('core_partner_webhooks');
    }
};
