<?php

declare(strict_types=1);

namespace Modules\Party\Domain\Enums;

enum KycDocumentType: string
{
    case Akta = 'akta';
    case Nib = 'nib';
    case Npwp = 'npwp';
    case Siup = 'siup';
    case Tdp = 'tdp';
    case SertifikatHalal = 'sertifikat_halal';
    case Iso = 'iso';
    case Bpom = 'bpom';
    case Gmp = 'gmp';
    case Ktp = 'ktp';
    case Passport = 'passport';
}
