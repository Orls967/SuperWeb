<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Application\Services;

use Modules\Manufacturing\Contracts\ProcessOutputRecorder;

class ProcessOutputRecorderService implements ProcessOutputRecorder
{
    public function recordProcessOutput(array $data): array
    {
        return [
            'output_batch_id' => 'MFG-SMELTER-'.$data['batch_code'],
            'status' => 'RECORDED',
        ];
    }
}
