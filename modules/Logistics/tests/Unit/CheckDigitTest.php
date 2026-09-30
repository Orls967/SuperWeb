<?php

declare(strict_types=1);

namespace Modules\Logistics\tests\Unit;

use InvalidArgumentException;
use Modules\Logistics\Domain\ValueObjects\ImoNumberValidator;
use Modules\Logistics\Domain\ValueObjects\Iso6346Validator;
use PHPUnit\Framework\TestCase;

class CheckDigitTest extends TestCase
{
    public function test_valid_imo_numbers(): void
    {
        $this->assertTrue(ImoNumberValidator::isValid('9074729'));
        $this->assertTrue(ImoNumberValidator::isValid('IMO 9074729'));
        $this->assertTrue(ImoNumberValidator::isValid('9241061'));
        $this->assertTrue(ImoNumberValidator::isValid('9315800'));

        $this->assertEquals('9074729', ImoNumberValidator::validate('IMO 9074729'));
    }

    public function test_invalid_imo_numbers(): void
    {
        $this->assertFalse(ImoNumberValidator::isValid('9074728')); // Salah check digit (harusnya 9)
        $this->assertFalse(ImoNumberValidator::isValid('9241065')); // Salah check digit (harusnya 1)
        $this->assertFalse(ImoNumberValidator::isValid('12345'));   // Kurang dari 7 digit
        $this->assertFalse(ImoNumberValidator::isValid('ABCDEFG')); // Non numerik

        $this->expectException(InvalidArgumentException::class);
        ImoNumberValidator::validate('9074728');
    }

    public function test_valid_iso_6346_container_numbers(): void
    {
        $this->assertTrue(Iso6346Validator::isValid('CSQU3054383'));
        $this->assertTrue(Iso6346Validator::isValid('csqu 305438-3'));

        $generated = Iso6346Validator::generate('SRX', 'U', 123456);
        $this->assertTrue(Iso6346Validator::isValid($generated));

        $generated2 = Iso6346Validator::generate('SRX', 'J', 999999);
        $this->assertTrue(Iso6346Validator::isValid($generated2));
    }

    public function test_invalid_iso_6346_container_numbers(): void
    {
        $this->assertFalse(Iso6346Validator::isValid('CSQU3054384')); // Salah check digit (seharusnya 3)
        $this->assertFalse(Iso6346Validator::isValid('CSQA3054383')); // Kategori 'A' salah (harus U, J, atau Z)
        $this->assertFalse(Iso6346Validator::isValid('CSQU30543'));   // Kurang panjang
        $this->assertFalse(Iso6346Validator::isValid('12345678901')); // Bukan kode huruf di depan

        $this->expectException(InvalidArgumentException::class);
        Iso6346Validator::validate('CSQU3054384');
    }
}
