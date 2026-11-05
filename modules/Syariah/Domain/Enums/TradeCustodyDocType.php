<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Enums;

enum TradeCustodyDocType: string
{
    case BILL_OF_LADING = 'bill_of_lading';
    case AIR_WAYBILL = 'air_waybill';
    case SEA_WAYBILL = 'sea_waybill';
    case WAREHOUSE_RECEIPT = 'warehouse_receipt';
    case CERTIFICATE_OF_ORIGIN = 'certificate_of_origin';
}
