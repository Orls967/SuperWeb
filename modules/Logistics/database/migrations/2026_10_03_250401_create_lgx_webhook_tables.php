<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgx_webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->string('secret', 128);
            $table->json('events')->comment('Array of subscribed event types');
            $table->boolean('is_active')->default(true);
            $table->foreignId('shipper_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('lgx_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('endpoint_id')->constrained('lgx_webhook_endpoints')->cascadeOnDelete();
            $table->string('event_type', 50);
            $table->json('payload');
            $table->string('idempotency_key', 128)->unique();
            $table->unsignedTinyInteger('attempt')->default(0);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->string('status', 20)->default('pending')->comment('pending, delivered, failed, dead_letter');
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_retry_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_webhook_deliveries');
        Schema::dropIfExists('lgx_webhook_endpoints');
    }
};
