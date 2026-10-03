<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_audit_logs', function (Blueprint $table) {
            $table->string('correlation_id', 64)->nullable()->after('action')->index();
            $table->string('impact_type', 32)->nullable()->after('correlation_id')->index(); // financial, state, ownership, security
        });
    }

    public function down(): void
    {
        Schema::table('core_audit_logs', function (Blueprint $table) {
            $table->dropIndex(['correlation_id']);
            $table->dropIndex(['impact_type']);
            $table->dropColumn(['correlation_id', 'impact_type']);
        });
    }
};
