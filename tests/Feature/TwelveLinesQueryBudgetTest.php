<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\TwelveLinesUltraSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Services\GroupCommandCenterExecutiveService;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Tests\TestCase;

class TwelveLinesQueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_ultra_seeder_executes_within_memory_and_query_budget(): void
    {
        $memoryBefore = memory_get_usage();

        $seeder = new TwelveLinesUltraSeeder;
        $seeder->run();

        $memoryAfter = memory_get_usage();
        $memoryDeltaMb = ($memoryAfter - $memoryBefore) / (1024 * 1024);

        $this->assertLessThan(64.0, $memoryDeltaMb);
        $this->assertGreaterThan(0, HotelRoom::query()->count());
        $this->assertGreaterThan(0, EntertainmentEvent::query()->count());
    }

    public function test_critical_endpoints_and_command_center_query_budget(): void
    {
        $seeder = new TwelveLinesUltraSeeder;
        $seeder->run();

        DB::enableQueryLog();
        DB::flushQueryLog();

        $start = microtime(true);
        $service = app(GroupCommandCenterExecutiveService::class);
        $overview = $service->getExecutiveOverview();
        $durationMs = (microtime(true) - $start) * 1000;

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(50, count($queries));
        $this->assertLessThan(500.0, $durationMs);
        $this->assertIsArray($overview['pnl_consolidated']);
    }
}
