<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Services;

use Modules\Platform\Domain\Models\ReleaseTrain;

class ReleaseNotesGeneratorService
{
    public function generateMarkdown(ReleaseTrain $train): string
    {
        $train->loadMissing(['changeRequests', 'hotfixes']);

        $md = "# Catatan Rilis: {$train->version} ({$train->train_code})\n\n";
        $md .= "**Status**: {$train->status}  \n";
        $md .= '**Jadwal Deploy**: '.($train->scheduled_deploy_at?->toIso8601String() ?? 'N/A')."  \n\n";

        $md .= "## 1. Daftar Perubahan (Change Requests)\n";
        if ($train->changeRequests->isEmpty()) {
            $md .= "_Tidak ada perubahan tercatat dalam rilis ini._\n\n";
        } else {
            foreach ($train->changeRequests as $cr) {
                $md .= "- **[{$cr->reference_number}]** {$cr->title} (Modul: `{$cr->target_module}`, Risiko: `{$cr->risk_tier}`)\n";
            }
            $md .= "\n";
        }

        $md .= "## 2. Hotfixes Terkait\n";
        if ($train->hotfixes->isEmpty()) {
            $md .= "_Tidak ada hotfix khusus dalam rilis ini._\n\n";
        } else {
            foreach ($train->hotfixes as $hf) {
                $md .= "- **[{$hf->hotfix_number}]** Severity: `{$hf->severity}`, Status: `{$hf->isolation_status}`\n";
            }
            $md .= "\n";
        }

        return $md;
    }
}
