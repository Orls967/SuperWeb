<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_products', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable()->after('productable_id')
                ->constrained('users')->nullOnDelete();
            $table->index(['seller_id', 'is_listed']);
        });

        Schema::table('store_orders', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('handover_at')->nullable()->after('paid_at');
            $table->timestamp('received_at')->nullable()->after('handover_at');
            $table->timestamp('auto_capture_at')->nullable()->after('received_at');
            $table->timestamp('disputed_at')->nullable()->after('auto_capture_at');
            $table->text('dispute_reason')->nullable()->after('disputed_at');
        });
    }

    public function down(): void
    {
        Schema::table('store_orders', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
            $table->dropColumn([
                'seller_id',
                'handover_at',
                'received_at',
                'auto_capture_at',
                'disputed_at',
                'dispute_reason',
            ]);
        });

        Schema::table('store_products', function (Blueprint $table) {
            $table->dropIndex(['seller_id', 'is_listed']);
            $table->dropForeign(['seller_id']);
            $table->dropColumn('seller_id');
        });
    }
};
