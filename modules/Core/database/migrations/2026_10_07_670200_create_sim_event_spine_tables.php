<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sim_event_spine')) {
            Schema::create('sim_event_spine', function (Blueprint $table) {
                $table->id();
                $table->uuid('event_id')->unique();
                $table->string('topic')->index(); // auto.*, fintech.*, resto.*, etc.
                $table->string('event_name');
                $table->integer('version')->default(1);
                $table->json('payload');
                $table->string('idempotency_key')->unique();
                $table->timestamp('occurred_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sim_event_consumers')) {
            Schema::create('sim_event_consumers', function (Blueprint $table) {
                $table->id();
                $table->string('consumer_group');
                $table->string('topic');
                $table->unsignedBigInteger('last_processed_offset')->default(0);
                $table->timestamps();

                $table->unique(['consumer_group', 'topic']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sim_event_consumers');
        Schema::dropIfExists('sim_event_spine');
    }
};
