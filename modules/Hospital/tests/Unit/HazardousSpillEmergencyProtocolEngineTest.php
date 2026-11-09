<?php

declare(strict_types=1);

namespace Modules\Hospital\Tests\Unit;

use Modules\Hospital\Domain\Services\HazardousSpillEmergencyProtocolEngine;
use PHPUnit\Framework\TestCase;

class HazardousSpillEmergencyProtocolEngineTest extends TestCase
{
    private HazardousSpillEmergencyProtocolEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new HazardousSpillEmergencyProtocolEngine();
    }

    public function test_cytotoxic_chemotherapy_spill_triggers_code_orange_and_purple_bag(): void
    {
        $result = $this->engine->assessSpillEmergency(
            HazardousSpillEmergencyProtocolEngine::SPILL_TYPE_CYTOTOXIC_CHEMO,
            15.0, // 15 mL -> Major
            false,
            false
        );

        $this->assertSame(HazardousSpillEmergencyProtocolEngine::SEVERITY_MAJOR, $result['severity']);
        $this->assertTrue($result['requires_code_orange_alarm']);
        $this->assertSame('PURPLE_BAG_CYTOTOXIC', $result['waste_bin_type']);
        $this->assertContains('DOUBLE_CHEMO_RATED_GLOVES', $result['required_ppe']);
    }

    public function test_mercury_spill_requires_sulfur_amalgamation_and_brown_bag(): void
    {
        $result = $this->engine->assessSpillEmergency(
            HazardousSpillEmergencyProtocolEngine::SPILL_TYPE_MERCURY_METALLIC,
            2.0,
            false,
            false
        );

        $this->assertSame('BROWN_BAG_CHEMICAL_HEAVY_METAL', $result['waste_bin_type']);
        $this->assertSame('SULFUR_POWDER_AMALGAMATION_MATRIX', $result['neutralizing_agent']);
    }

    public function test_minor_blood_spill_uses_yellow_biohazard_bag(): void
    {
        $result = $this->engine->assessSpillEmergency(
            HazardousSpillEmergencyProtocolEngine::SPILL_TYPE_INFECTIOUS_BLOOD,
            20.0,
            false,
            false
        );

        $this->assertSame(HazardousSpillEmergencyProtocolEngine::SEVERITY_MINOR, $result['severity']);
        $this->assertSame('YELLOW_BAG_BIOHAZARD', $result['waste_bin_type']);
        $this->assertSame('SODIUM_HYPOCHLORITE_0_5_PERCENT', $result['neutralizing_agent']);
    }
}
