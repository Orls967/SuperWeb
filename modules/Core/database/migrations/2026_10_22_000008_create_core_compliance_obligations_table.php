<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('core_compliance_obligations')) {
            Schema::create('core_compliance_obligations', function (Blueprint $table): void {
                $table->id();
                $table->string('permit_code', 64)->unique();
                $table->string('domain_code', 32);
                $table->string('permit_name', 128);
                $table->string('issuing_authority', 128);
                $table->date('expiration_date');
                $table->unsignedSmallInteger('alert_lead_days')->default(30);
                $table->string('status', 24)->default('VALID');
                $table->timestamps();

                $table->index(['domain_code', 'expiration_date', 'status'], 'core_compliance_domain_exp_stat_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('core_compliance_obligations');
    }
};
