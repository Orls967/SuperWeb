<?php

declare(strict_types=1);

namespace Tests\Keamanan;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hcm\Domain\Models\Employee;
use Modules\Party\Domain\Enums\PartyType;
use Modules\Party\Domain\Models\Party;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('keamanan')]
class SensitiveDataExposureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * S-08: Sensitive PII (NIK / NPWP) must not be exposed when models are serialized to array / JSON.
     */
    public function test_party_model_hides_sensitive_pii_in_serialization(): void
    {
        $party = new Party([
            'name' => 'PT Sumber Rejeki',
            'type' => PartyType::Company,
            'nik' => '3171012345678901',
            'npwp' => '012345678901234',
        ]);

        $array = $party->toArray();

        // Plaintext or raw NIK/NPWP must be hidden from serialized output
        $this->assertArrayNotHasKey('nik', $array, 'Decrypted NIK should be hidden from model array serialization');
        $this->assertArrayNotHasKey('npwp', $array, 'Decrypted NPWP should be hidden from model array serialization');
    }

    public function test_employee_model_hides_nik_in_serialization(): void
    {
        $employee = new Employee([
            'nik' => '3271012345678902',
            'full_name' => 'Budi Santoso',
            'employment_status' => 'permanent',
        ]);

        $array = $employee->toArray();

        $this->assertArrayNotHasKey('nik', $array, 'Employee NIK should be hidden from serialization');
    }
}
