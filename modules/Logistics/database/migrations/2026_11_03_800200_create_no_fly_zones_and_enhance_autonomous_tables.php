<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lgx_no_fly_zones')) {
            Schema::create('lgx_no_fly_zones', function (Blueprint $table) {
                $table->id();
                $table->string('zone_code', 32)->unique();
                $table->string('name', 64);
                $table->text('polygon_coordinates_json'); // list of {"x": int, "y": int}
                $table->integer('min_altitude_meters')->default(0);
                $table->integer('max_altitude_meters')->default(500);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('lgx_temp_breach_holds')) {
            Schema::table('lgx_temp_breach_holds', function (Blueprint $table) {
                if (! Schema::hasColumn('lgx_temp_breach_holds', 'leg_id')) {
                    $table->unsignedBigInteger('leg_id')->nullable()->after('shipment_code');
                }
                if (! Schema::hasColumn('lgx_temp_breach_holds', 'episode_id')) {
                    $table->string('episode_id', 32)->nullable()->after('leg_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lgx_temp_breach_holds')) {
            Schema::table('lgx_temp_breach_holds', function (Blueprint $table) {
                if (Schema::hasColumn('lgx_temp_breach_holds', 'episode_id')) {
                    $table->dropColumn('episode_id');
                }
                if (Schema::hasColumn('lgx_temp_breach_holds', 'leg_id')) {
                    $table->dropColumn('leg_id');
                }
            });
        }

        Schema::dropIfExists('lgx_no_fly_zones');
    }
};
