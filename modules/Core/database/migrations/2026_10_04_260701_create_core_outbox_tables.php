<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 100)->index();
            $table->string('aggregate_type', 100)->nullable()->index();
            $table->string('aggregate_id', 64)->nullable()->index();
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->string('idempotency_key', 128)->unique();
            $table->string('status', 20)->default('pending')->index(); // pending, dispatched, failed, dead_letter
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('next_retry_at')->nullable()->index();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->timestamp('updated_at')->useCurrent();
        });

        Schema::create('core_outbox_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('target_type', 50)->default('webhook'); // webhook, listener, queue
            $table->string('target', 255); // URL or handler class
            $table->string('secret', 128)->nullable();
            $table->json('events')->comment('Array of subscribed event types or ["*"]');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('timeout_seconds')->default(10);
            $table->timestamps();
        });

        Schema::create('core_outbox_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outbox_id')->constrained('core_outbox')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('core_outbox_subscriptions')->nullOnDelete();
            $table->string('status', 20)->default('pending')->index(); // pending, success, failed, dead_letter
            $table->unsignedTinyInteger('attempt')->default(0);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();

            $table->index(['outbox_id', 'subscription_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_outbox_dispatches');
        Schema::dropIfExists('core_outbox_subscriptions');
        Schema::dropIfExists('core_outbox');
    }
};
