<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Security\BlindIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Hcm\Application\Services\HcmService;
use Tests\TestCase;

class RotateBlindIndexCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.blind_index_pepper' => 'new-pepper-secret-testing-32bytes=']);
    }

    public function test_rotate_blind_index_dry_run_does_not_mutate_database(): void
    {
        $oldPepper = 'old-pepper-secret-testing-32bytes=';
        $nik = '3171012345670001';

        $hcmService = app(HcmService::class);
        $employee = $hcmService->registerEmployee([
            'employee_number' => 'EMP-ROT-001',
            'name' => 'John Doe',
            'nik' => $nik,
        ]);

        // Manually set old pepper index
        $oldBidx = BlindIndex::forNik($nik, $oldPepper);
        $employee->nik_bidx = $oldBidx;
        $employee->save();

        $this->assertSame($oldBidx, $employee->nik_bidx);

        $exitCode = Artisan::call('app:rotate-blind-index', [
            '--old-pepper' => $oldPepper,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $employee->refresh();
        $this->assertSame($oldBidx, $employee->nik_bidx);
    }

    public function test_rotate_blind_index_with_force_updates_records_idempotently(): void
    {
        $oldPepper = 'old-pepper-secret-testing-32bytes=';
        $nik = '3171012345670002';
        $newPepper = 'new-pepper-secret-testing-32bytes=';

        $hcmService = app(HcmService::class);
        $employee = $hcmService->registerEmployee([
            'employee_number' => 'EMP-ROT-002',
            'name' => 'Jane Doe',
            'nik' => $nik,
        ]);

        // Manually set old pepper index
        $oldBidx = BlindIndex::forNik($nik, $oldPepper);
        $employee->nik_bidx = $oldBidx;
        $employee->save();

        $this->assertNotSame(BlindIndex::forNik($nik, $newPepper), $employee->nik_bidx);

        // Run 1: updates to new pepper
        $exitCode1 = Artisan::call('app:rotate-blind-index', [
            '--old-pepper' => $oldPepper,
            '--force' => true,
        ]);
        $this->assertSame(0, $exitCode1);

        $employee->refresh();
        $this->assertSame(BlindIndex::forNik($nik, $newPepper), $employee->nik_bidx);

        // Run 2: idempotent, no updates
        $exitCode2 = Artisan::call('app:rotate-blind-index', [
            '--old-pepper' => $oldPepper,
            '--force' => true,
        ]);
        $this->assertSame(0, $exitCode2);
        $this->assertStringContainsString('Total record terdampak: 0', Artisan::output());
    }
}
