<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Actions;

use Modules\Marine\Application\Services\AquacultureBiosecurityProtocolService;

class AuditBiosecurityProtocolAction
{
    protected AquacultureBiosecurityProtocolService $biosecurityService;

    public function __construct(AquacultureBiosecurityProtocolService $biosecurityService)
    {
        $this->biosecurityService = $biosecurityService;
    }

    /**
     * @param  array<string, mixed>  $auditData
     * @return array<string, mixed>
     */
    public function execute(array $auditData): array
    {
        return $this->biosecurityService->auditPreStockingBiosecurity($auditData);
    }
}
