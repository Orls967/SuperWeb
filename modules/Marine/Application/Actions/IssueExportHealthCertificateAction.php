<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Actions;

use Modules\Marine\Application\Services\ExportDocumentationService;

class IssueExportHealthCertificateAction
{
    protected ExportDocumentationService $docService;

    public function __construct(ExportDocumentationService $docService)
    {
        $this->docService = $docService;
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(int $exportLotId, string $inspectorName): array
    {
        return $this->docService->issueHealthCertificate($exportLotId, $inspectorName);
    }
}
