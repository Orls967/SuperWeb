<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pty_legal_entities', function (Blueprint $table) {
            if (! Schema::hasColumn('pty_legal_entities', 'npwp_bidx')) {
                $table->char('npwp_bidx', 64)->nullable()->index()->after('npwp');
            }
        });

        Schema::table('pty_parties', function (Blueprint $table) {
            if (! Schema::hasColumn('pty_parties', 'nik')) {
                $table->text('nik')->nullable()->after('name_normalized');
            }
            if (! Schema::hasColumn('pty_parties', 'nik_bidx')) {
                $table->char('nik_bidx', 64)->nullable()->index()->after('nik');
            }
            if (! Schema::hasColumn('pty_parties', 'npwp')) {
                $table->text('npwp')->nullable()->after('nik_bidx');
            }
            if (! Schema::hasColumn('pty_parties', 'npwp_bidx')) {
                $table->char('npwp_bidx', 64)->nullable()->index()->after('npwp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pty_parties', function (Blueprint $table) {
            if (Schema::hasColumn('pty_parties', 'npwp_bidx')) {
                $table->dropColumn('npwp_bidx');
            }
            if (Schema::hasColumn('pty_parties', 'npwp')) {
                $table->dropColumn('npwp');
            }
            if (Schema::hasColumn('pty_parties', 'nik_bidx')) {
                $table->dropColumn('nik_bidx');
            }
            if (Schema::hasColumn('pty_parties', 'nik')) {
                $table->dropColumn('nik');
            }
        });

        Schema::table('pty_legal_entities', function (Blueprint $table) {
            if (Schema::hasColumn('pty_legal_entities', 'npwp_bidx')) {
                $table->dropColumn('npwp_bidx');
            }
        });
    }
};
