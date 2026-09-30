<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 50)->index();      // booking_completed, payment_received, etc.
            $table->string('icon', 20)->default('info'); // info, success, warning, danger
            $table->string('title');
            $table->text('body');
            $table->string('action_url')->nullable();
            $table->string('action_label')->nullable();
            $table->json('meta')->nullable();           // extra payload (booking_id, amount, etc.)
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at', 'created_at']);
        });

        Schema::create('platform_activity_log', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('module', 30)->index();     // autoserve, autodex, banking, store, crypto
            $table->string('event', 50)->index();      // booking_created, trade_executed, etc.
            $table->string('description');
            $table->nullableMorphs('subject');          // polymorphic to any model
            $table->json('properties')->nullable();     // extra details
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['module', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_activity_log');
        Schema::dropIfExists('platform_notifications');
    }
};
