<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_media_content_rights_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('license_code')->unique();
            $table->string('media_asset_id');
            $table->timestamp('window_expires_at');
            $table->boolean('distribution_stopped')->default(false); // 385.3 & 385.4
            $table->boolean('post_expiry_compensation_paid')->default(false); // 385.5 Edge case
            $table->timestamps();
        });

        Schema::create('global_media_ad_serving_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('serving_code')->unique();
            $table->string('license_code')->index();
            $table->boolean('serving_permitted')->default(true); // 385.4
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_media_ad_serving_campaigns');
        Schema::dropIfExists('global_media_content_rights_licenses');
    }
};
