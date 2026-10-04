<?php

declare(strict_types=1);

namespace Modules\Party\Domain\Enums;

enum PartyRoleType: string
{
    case Supplier = 'supplier';
    case Producer = 'producer';
    case Distributor = 'distributor';
    case Agent = 'agent';
    case Partner = 'partner';
    case Customer = 'customer';
    case Carrier = 'carrier';
    case Tenant = 'tenant';
    case Franchisee = 'franchisee';
    case Shipper = 'shipper';
}
