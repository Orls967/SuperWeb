<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('core_event_schemas')) {
            Schema::create('core_event_schemas', function (Blueprint $table) {
                $table->id();
                $table->string('event_name');
                $table->integer('version')->default(1);
                $table->string('producer_module');
                $table->json('consumer_modules');
                $table->json('payload_schema');
                $table->text('description')->nullable();
                $table->timestamps();

                $table->unique(['event_name', 'version']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('core_event_schemas');
    }
};
