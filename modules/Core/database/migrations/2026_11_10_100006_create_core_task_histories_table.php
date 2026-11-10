<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('core_task_histories')) {
            Schema::create('core_task_histories', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('task_id')->index();
                $table->string('actor_id')->nullable()->index();
                $table->string('action', 64)->index();
                $table->string('from_status', 32)->nullable();
                $table->string('to_status', 32)->index();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('task_id')
                    ->references('id')
                    ->on('core_tasks')
                    ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('core_task_histories');
    }
};
