<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_employees', function (Blueprint $table) {
            if (! Schema::hasColumn('hcm_employees', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('hcm_employees', 'nik')) {
                $table->text('nik')->nullable()->after('name');
            }
            if (! Schema::hasColumn('hcm_employees', 'nik_bidx')) {
                $table->char('nik_bidx', 64)->nullable()->index()->after('nik');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hcm_employees', function (Blueprint $table) {
            if (Schema::hasColumn('hcm_employees', 'nik_bidx')) {
                $table->dropColumn('nik_bidx');
            }
            if (Schema::hasColumn('hcm_employees', 'nik')) {
                $table->dropColumn('nik');
            }
            if (Schema::hasColumn('hcm_employees', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });
    }
};
