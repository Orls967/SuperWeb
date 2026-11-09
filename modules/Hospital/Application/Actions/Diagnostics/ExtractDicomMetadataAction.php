<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Diagnostics;

use Modules\Hospital\Application\Services\DicomWebMetadataExtractor;
use Modules\Hospital\Domain\Models\ImagingStudy;
use RuntimeException;

class ExtractDicomMetadataAction
{
    public function __construct(
        protected DicomWebMetadataExtractor $extractor
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(int $studyId): array
    {
        /** @var ImagingStudy|null $study */
        $study = ImagingStudy::find($studyId);

        if (! $study) {
            throw new RuntimeException("Studi radiologi ID [{$studyId}] tidak ditemukan.");
        }

        return $this->extractor->extractStudyViewerMetadata($study);
    }
}
