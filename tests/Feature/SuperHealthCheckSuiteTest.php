<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SuperHealthCheckSuiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_health_check_passes_cleanly(): void
    {
        $exitCode = Artisan::call('super:health-check');
        $this->assertSame(0, $exitCode);
    }
}
