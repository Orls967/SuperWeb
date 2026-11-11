<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgx_tracking_events_archive', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('archive_run_ref');
            $table->unsignedBigInteger('shipment_ref');
            $table->unsignedInteger('sequence')->default(1);
            $table->string('event_type', 64);
            $table->string('description', 500)->nullable();
            $table->json('payload')->nullable();
            $table->dateTime('occurred_at');
            $table->string('prev_hash', 64);
            $table->string('hash', 64);
            $table->timestamp('original_created_at')->nullable();
            $table->timestamp('archived_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_tracking_events_archive');
    }
};
