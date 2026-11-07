<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use DomainException;
use Modules\Shared\Domain\Algorithms\Stats\LinearBaselineRegression;
use Modules\Shared\Domain\Algorithms\Stats\LinearBaselineRegressionCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Stats\LinearBaselineRegressionResult;
use PHPUnit\Framework\TestCase;

class LinearBaselineRegressionTest extends TestCase
{
    public function test_linear_baseline_regression_prompt_vector(): void
    {
        $regression = new LinearBaselineRegression();

        $xPoints = [10, 20, 30, 40, 50];
        $yPoints = [230, 410, 620, 800, 1010];

        $newX = 35;
        $actualY = 860;

        $result = $regression->fitAndPredict($xPoints, $yPoints, $newX, $actualY, 1500);

        // Slope b = 39/2 = 19.5
        $this->assertEquals(39, $result->slope['numerator']);
        $this->assertEquals(2, $result->slope['denominator']);
        $this->assertEquals(19.5, $result->slope['float_val']);

        // Intercept a = 29
        $this->assertEquals(29.0, $result->intercept['float_val']);

        // Residuals [6, -9, 6, -9, 6]
        $this->assertEquals([6.0, -9.0, 6.0, -9.0, 6.0], $result->residuals);

        // Point 35: pred 711.5, actual 860, excess 148.5, bps 2087
        $this->assertEquals(711.5, $result->predictedY);
        $this->assertEquals(148.5, $result->excessY);
        $this->assertEquals(2087, $result->excessBps);
        $this->assertTrue($result->isAnomaly);
    }

    public function test_corrupted_certificate_throws_domain_exception(): void
    {
        $verifier = new LinearBaselineRegressionCertificateVerifier();

        $corruptResult = new LinearBaselineRegressionResult(
            slope: ['numerator' => 39, 'denominator' => 2, 'float_val' => 19.5],
            intercept: ['numerator' => 29, 'denominator' => 1, 'float_val' => 29.0],
            residuals: [6.0, -9.0, 6.0, -9.0, 6.0],
            newX: 35.0,
            predictedY: 600.0, // invalid: should be 711.5!
            actualY: 860.0,
            excessY: 260.0,
            excessBps: 4333,
            thresholdBps: 1500,
            isAnomaly: true,
            certificateHash: 'invalid_hash',
            certificateData: []
        );

        $this->expectException(DomainException::class);
        $verifier->verify($corruptResult);
    }
}
