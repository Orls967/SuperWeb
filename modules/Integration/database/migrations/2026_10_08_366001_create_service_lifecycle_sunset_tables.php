<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_api_service_sunsets', function (Blueprint $table) {
            $table->id();
            $table->string('api_service_code')->unique();
            $table->string('current_lifecycle_state'); // SUPPORTED, DEPRECATED, SUNSET
            $table->integer('active_consumer_count')->default(0);
            $table->boolean('has_valid_dated_waiver')->default(false); // 366.2, 366.4, 366.5 Edge case
            $table->boolean('archived_evidence_retained')->default(false); // 366.3, 366.4, 366.6 Risk
            $table->boolean('credentials_revoked')->default(false);
            $table->boolean('sunset_completed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_api_service_sunsets');
    }
};
