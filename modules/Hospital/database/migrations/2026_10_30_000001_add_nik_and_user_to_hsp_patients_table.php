<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hsp_patients', function (Blueprint $table): void {
            if (! Schema::hasColumn('hsp_patients', 'national_id_nik')) {
                $table->text('national_id_nik')->nullable();
            }
            if (! Schema::hasColumn('hsp_patients', 'nik_blind_index')) {
                $table->string('nik_blind_index', 64)->nullable()->index();
            }
            if (! Schema::hasColumn('hsp_patients', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('hsp_patients', function (Blueprint $table): void {
            if (Schema::hasColumn('hsp_patients', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
            if (Schema::hasColumn('hsp_patients', 'nik_blind_index')) {
                $table->dropColumn('nik_blind_index');
            }
            if (Schema::hasColumn('hsp_patients', 'national_id_nik')) {
                $table->dropColumn('national_id_nik');
            }
        });
    }
};
