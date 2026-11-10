<?php

declare(strict_types=1);

namespace Tests\Acceptance\Treasury;

use InvalidArgumentException;
use Modules\Treasury\Domain\Algorithms\Certificates\SeasonalCashForecastCertificateVerifier;
use Modules\Treasury\Domain\Algorithms\SeasonalCashForecaster;
use Modules\Treasury\Domain\Exceptions\InvalidCertificateException;
use Tests\TestCase;

class SeasonalCashForecastAlgorithmVectorTest extends TestCase
{
    public function test_seasonal_cash_forecaster_matches_all_prompt_vectors(): void
    {
        $forecaster = new SeasonalCashForecaster;

        $history12 = [100, 80, 60, 140, 110, 90, 70, 150, 120, 100, 80, 160];
        $result = $forecaster->forecast(historyWeeks: $history12, seasonPeriod: 4, horizonWeeks: 13);

        $this->assertSame(105, (int) round($result->overallMean));
        $this->assertSame([110, 90, 70, 150], $result->seasonalMeans);
        $this->assertSame([1047, 857, 666, 1428], $result->seasonalIndicesPermille);

        $this->assertSame(32411, $result->interceptNumerator);
        $this->assertSame(363, $result->interceptDenominator);
        $this->assertSame(11408, $result->slopeNumerator);
        $this->assertSame(4719, $result->slopeDenominator);

        $expectedForecast = [126, 105, 83, 182, 136, 113, 90, 196, 146, 122, 96, 210, 156];
        $this->assertSame($expectedForecast, $result->projectedWeeks);
        $this->assertSame(1761, array_sum($result->projectedWeeks));

        $this->assertTrue(SeasonalCashForecastCertificateVerifier::verify($result->certificate));
    }

    public function test_not_full_cycles_history_throws_exception(): void
    {
        $forecaster = new SeasonalCashForecaster;
        $history10 = [100, 80, 60, 140, 110, 90, 70, 150, 120, 100];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('HISTORY_NOT_FULL_CYCLES');
        $forecaster->forecast(historyWeeks: $history10, seasonPeriod: 4, horizonWeeks: 13);
    }

    public function test_insufficient_history_throws_exception(): void
    {
        $forecaster = new SeasonalCashForecaster;
        $history4 = [100, 80, 60, 140];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('INSUFFICIENT_HISTORY');
        $forecaster->forecast(historyWeeks: $history4, seasonPeriod: 4, horizonWeeks: 13);
    }

    public function test_corrupted_seasonal_forecast_certificate_throws_exception(): void
    {
        $forecaster = new SeasonalCashForecaster;
        $result = $forecaster->forecast([100, 80, 60, 140, 110, 90, 70, 150], 4, 13);

        $corruptCert = $result->certificate;
        $corruptCert->sha256 = 'corrupted_hash';

        $this->expectException(InvalidCertificateException::class);
        SeasonalCashForecastCertificateVerifier::assertValid($corruptCert);
    }
}
