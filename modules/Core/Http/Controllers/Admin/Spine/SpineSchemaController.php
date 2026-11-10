<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Admin\Spine;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Core\Domain\Algorithms\Events\SchemaAdditiveCompatibilityChecker;
use Modules\Core\Domain\Models\CoreEventSchema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpineSchemaController extends Controller
{
    public function __construct(
        protected SchemaAdditiveCompatibilityChecker $checker
    ) {}

    public function index(Request $request): View
    {
        $schemas = CoreEventSchema::orderBy('event_name')->orderBy('version')->get();
        $grouped = $schemas->groupBy('event_name');

        // Evaluasi kepatuhan A6 per versi
        $evaluations = [];
        foreach ($grouped as $name => $versions) {
            $prev = null;
            foreach ($versions as $ver) {
                if ($prev !== null) {
                    $violations = $this->checker->checkCompatibility(
                        (array) $prev->payload_schema,
                        (array) $ver->payload_schema
                    );
                    $evaluations[$ver->id] = [
                        'is_compatible' => empty($violations),
                        'violations' => $violations,
                        'compared_to' => $prev->version,
                    ];
                } else {
                    $evaluations[$ver->id] = [
                        'is_compatible' => true,
                        'violations' => [],
                        'compared_to' => null,
                    ];
                }
                $prev = $ver;
            }
        }

        return view('core::admin.spine.schemas', compact('grouped', 'evaluations'));
    }

    public function exportCsv(): StreamedResponse
    {
        $schemas = CoreEventSchema::all();

        return response()->streamDownload(function () use ($schemas) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'event_name', 'version', 'producer_module', 'consumer_modules', 'description', 'updated_at']);
            foreach ($schemas as $s) {
                fputcsv($handle, [
                    $s->id,
                    $s->event_name,
                    $s->version,
                    $s->producer_module,
                    implode(';', (array) $s->consumer_modules),
                    $s->description ?? '',
                    (string) $s->updated_at,
                ]);
            }
            fclose($handle);
        }, 'event_schemas_export_'.date('Ymd_His').'.csv');
    }
}
