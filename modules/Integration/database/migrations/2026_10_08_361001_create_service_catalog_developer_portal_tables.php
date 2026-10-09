<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_developer_portal_capabilities', function (Blueprint $table) {
            $table->id();
            $table->string('capability_code')->unique();
            $table->string('service_name');
            $table->string('owner_team');
            $table->string('lifecycle_status'); // ACTIVE, DEPRECATED, RETIRED
            $table->boolean('catalog_entry_registered')->default(false); // 361.1 & 361.5 Edge case
            $table->boolean('publish_permitted')->default(false);
            $table->timestamps();
        });

        Schema::create('service_developer_portal_sandboxes', function (Blueprint $table) {
            $table->id();
            $table->string('sandbox_code')->unique();
            $table->string('capability_code')->index();
            $table->string('requester_team');
            $table->boolean('sandbox_scope_isolated')->default(true); // 361.2 & 361.4
            $table->boolean('approval_granted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_developer_portal_sandboxes');
        Schema::dropIfExists('service_developer_portal_capabilities');
    }
};
