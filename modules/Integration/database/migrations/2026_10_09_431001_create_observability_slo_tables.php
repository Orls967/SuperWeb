<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plt_observability_signals', function (Blueprint $table) {
            $table->id();
            $table->string('service_name');
            $table->decimal('traffic_rps', 12, 2);
            $table->decimal('error_rate_percent', 5, 2);
            $table->decimal('latency_p99_ms', 10, 2);
            $table->decimal('saturation_percent', 5, 2);
            $table->string('business_overlay_metric'); // orders, claims, bookings (431.1)
            $table->decimal('business_volume', 15, 2);
            $table->timestamp('freshness_timestamp'); // 431.6 freshness label
            $table->timestamps();
        });

        Schema::create('plt_observability_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('alert_code')->unique();
            $table->string('service_name');
            $table->string('severity'); // page, ticket, info
            $table->string('dedup_fingerprint'); // 431.2 dedup
            $table->string('runbook_url'); // 431.2, 431.4 runbook link
            $table->boolean('is_suppressed')->default(false); // 431.5 alert storm grouping/suppression
            $table->string('ticket_id')->nullable(); // auto ticket
            $table->string('status')->default('firing'); // firing, resolved, suppressed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plt_observability_alerts');
        Schema::dropIfExists('plt_observability_signals');
    }
};
