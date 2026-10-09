<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pemilik akun portal pemasok — sumber kebenaran isolasi data portal
        // (entity_id RBAC bertipe numerik sehingga tidak cocok untuk UUID).
        Schema::table('sup_suppliers', function (Blueprint $table) {
            $table->foreignId('owner_user_id')->nullable()->after('party_id')
                ->constrained('users')->nullOnDelete()
                ->comment('Akun yang berhak membuka portal pemasok ini');
            $table->index('owner_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('sup_suppliers', function (Blueprint $table) {
            $table->dropIndex(['owner_user_id']);
            $table->dropColumn('owner_user_id');
        });
    }
};
