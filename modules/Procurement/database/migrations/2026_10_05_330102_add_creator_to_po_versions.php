<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('prc_po_versions', 'created_by_user_id')) {
            Schema::table('prc_po_versions', function (Blueprint $table) {
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('prc_po_versions', 'created_by_user_id')) {
            Schema::table('prc_po_versions', function (Blueprint $table) {
                $table->dropColumn('created_by_user_id');
            });
        }
    }
};
