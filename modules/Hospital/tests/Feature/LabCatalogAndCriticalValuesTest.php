<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hospital\Application\Services\CriticalRangeNotifierService;
use Modules\Hospital\Domain\Models\LabCriticalRange;
use Tests\TestCase;

class LabCatalogAndCriticalValuesTest extends TestCase
{
    use RefreshDatabase;

    private CriticalRangeNotifierService $notifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notifier = app(CriticalRangeNotifierService::class);

        LabCriticalRange::create([
            'test_code' => 'POTASSIUM',
            'panic_low' => 2.50,
            'panic_high' => 6.50,
            'notification_protocol' => 'CRITICAL_HOLD_AND_PAGE',
        ]);

        LabCriticalRange::create([
            'test_code' => 'TROPONIN_I',
            'panic_low' => null,
            'panic_high' => 0.04,
            'notification_protocol' => 'STAT_CODE_HEART_PAGE',
        ]);
    }

    public function test_potassium_severe_hypokalemia_triggers_panic_alert(): void
    {
        // 2.1 mEq/L is below panic low (2.50)
        $evaluation = $this->notifier->evaluateCriticalPanic('POTASSIUM', 2.10);

        $this->assertTrue($evaluation['is_panic']);
        $this->assertSame('CRITICAL_HOLD_AND_PAGE', $evaluation['protocol']);
        $this->assertStringContainsString('CRITICAL PANIC VALUE ALERT', $evaluation['message']);
    }

    public function test_troponin_elevation_triggers_stat_cardiology_page(): void
    {
        // 0.85 ng/mL is far above panic high (0.04)
        $evaluation = $this->notifier->evaluateCriticalPanic('TROPONIN_I', 0.85);

        $this->assertTrue($evaluation['is_panic']);
        $this->assertSame('STAT_CODE_HEART_PAGE', $evaluation['protocol']);
    }

    public function test_normal_lab_value_reports_standard_without_panic(): void
    {
        // 4.2 mEq/L is normal
        $evaluation = $this->notifier->evaluateCriticalPanic('POTASSIUM', 4.20);

        $this->assertFalse($evaluation['is_panic']);
        $this->assertSame('STANDARD_REPORT', $evaluation['protocol']);
    }
}
