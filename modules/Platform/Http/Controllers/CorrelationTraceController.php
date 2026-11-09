<?php

declare(strict_types=1);

namespace Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Platform\Domain\Actions\TraceCorrelationChainAction;

class CorrelationTraceController extends Controller
{
    public function show(string $correlationId, TraceCorrelationChainAction $tracer): View
    {
        $traceData = $tracer->trace($correlationId);

        return view('platform::dev.trace.show', [
            'trace' => $traceData,
        ]);
    }
}
