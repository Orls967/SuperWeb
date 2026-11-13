<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wms_unit_costs')) {
            Schema::create('wms_unit_costs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('warehouse_id', 64)->nullable()->index();
                $table->unsignedBigInteger('cost_idr');
                $table->string('method', 16)->default('std'); // avg, std, fifo
                $table->date('effective_date')->index();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();

                $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
                $table->unique(['product_id', 'warehouse_id', 'effective_date', 'method'], 'wms_unit_costs_prod_wh_date_method_uq');
            });
        }

        if (! Schema::hasTable('wms_task_events')) {
            Schema::create('wms_task_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('task_id')->index();
                $table->string('from_status', 32);
                $table->string('to_status', 32);
                $table->string('action', 48);
                $table->unsignedBigInteger('actor_user_id')->nullable()->index();
                $table->string('reason', 255)->nullable();
                $table->json('payload_json')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('task_id')->references('id')->on('wms_tasks')->cascadeOnDelete();
                $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wms_task_events');
        Schema::dropIfExists('wms_unit_costs');
    }
};
