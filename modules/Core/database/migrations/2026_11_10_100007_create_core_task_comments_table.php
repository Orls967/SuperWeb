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
        if (! Schema::hasTable('core_task_comments')) {
            Schema::create('core_task_comments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('task_id')->index();
                $table->string('user_id')->nullable()->index();
                $table->string('author_name', 128);
                $table->text('comment');
                $table->json('attachments')->nullable();
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
        Schema::dropIfExists('core_task_comments');
    }
};
