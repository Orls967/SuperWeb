<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ctr_clause_templates')) {
            Schema::table('ctr_clause_templates', function (Blueprint $table): void {
                if (! Schema::hasColumn('ctr_clause_templates', 'dispute_count')) {
                    $table->integer('dispute_count')->default(0)->after('is_active');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ctr_clause_templates')) {
            Schema::table('ctr_clause_templates', function (Blueprint $table): void {
                if (Schema::hasColumn('ctr_clause_templates', 'dispute_count')) {
                    $table->dropColumn('dispute_count');
                }
            });
        }
    }
};
