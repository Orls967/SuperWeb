<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\BlindIndex;
use RuntimeException;
use Tests\TestCase;

class BlindIndexTest extends TestCase
{
    public function test_deterministic_generation_with_fixed_vector(): void
    {
        $nik = '3171012345670001';
        $pepper = 'fixed-test-pepper-12345';

        $expectedHash = hash_hmac('sha256', $nik, $pepper);

        $index1 = BlindIndex::forNik($nik, $pepper);
        $index2 = BlindIndex::forNik($nik, $pepper);

        $this->assertSame($expectedHash, $index1);
        $this->assertSame($index1, $index2);
    }

    public function test_different_peppers_produce_different_indices(): void
    {
        $nik = '3171012345670001';

        $indexA = BlindIndex::forNik($nik, 'pepper-alpha');
        $indexB = BlindIndex::forNik($nik, 'pepper-beta');

        $this->assertNotSame($indexA, $indexB);
    }

    public function test_nik_and_npwp_normalization_strips_separators_and_spaces(): void
    {
        $pepper = 'test-pepper';

        $rawNik = '31.71.01-234567-0001';
        $cleanNik = '3171012345670001';
        $spacedNik = "  31 71 01\t234567 0001 \n";

        $indexRaw = BlindIndex::forNik($rawNik, $pepper);
        $indexClean = BlindIndex::forNik($cleanNik, $pepper);
        $indexSpaced = BlindIndex::forNik($spacedNik, $pepper);

        $this->assertSame($indexClean, $indexRaw);
        $this->assertSame($indexClean, $indexSpaced);

        $rawNpwp = '01.234.567.8-901.000';
        $cleanNpwp = '012345678901000';
        $indexNpwpRaw = BlindIndex::forNpwp($rawNpwp, $pepper);
        $indexNpwpClean = BlindIndex::forNpwp($cleanNpwp, $pepper);

        $this->assertSame($indexNpwpClean, $indexNpwpRaw);
    }

    public function test_empty_or_null_values_return_null(): void
    {
        $pepper = 'test-pepper';

        $this->assertNull(BlindIndex::generate(null, $pepper));
        $this->assertNull(BlindIndex::generate('', $pepper));
        $this->assertNull(BlindIndex::generate('   ', $pepper));
        $this->assertNull(BlindIndex::forNik(null, $pepper));
        $this->assertNull(BlindIndex::forNik('', $pepper));
        $this->assertNull(BlindIndex::forNpwp(null, $pepper));
        $this->assertNull(BlindIndex::forNpwp('', $pepper));
    }

    public function test_throws_exception_in_production_when_pepper_is_empty(): void
    {
        $previousEnv = app()->environment();
        $previousConfig = config('app.blind_index_pepper');
        $previousAppEnv = config('app.env');

        try {
            // Set to production with empty pepper
            config(['app.env' => 'production']);
            config(['app.blind_index_pepper' => '']);
            putenv('BLIND_INDEX_PEPPER=');

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('BLIND_INDEX_PEPPER is required and cannot be empty in production environment.');

            BlindIndex::pepper();
        } finally {
            // Restore configuration
            config(['app.env' => $previousAppEnv]);
            config(['app.blind_index_pepper' => $previousConfig]);
            putenv('BLIND_INDEX_PEPPER='.($previousConfig ?? 'test-blind-index-pepper-for-phpunit-suites-minimum-32-chars-long'));
        }
    }

    public function test_uses_config_pepper_by_default_when_not_overridden(): void
    {
        $configPepper = 'my-configured-app-pepper';
        config(['app.blind_index_pepper' => $configPepper]);

        $index = BlindIndex::forNik('3171012345670001');
        $expected = hash_hmac('sha256', '3171012345670001', $configPepper);

        $this->assertSame($expected, $index);
    }
}
