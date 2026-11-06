<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RecordGeneratorTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'generator_asset_code' => ['required', 'string', 'max:50'],
            'site_name' => ['required', 'string', 'max:120'],
            'run_type' => ['required', 'string', 'in:weekly_no_load_test,weekly_load_test,grid_outage_support,black_start'],
            'start_time' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'fuel_consumed_liters' => ['required', 'integer', 'min:0'],
            'load_percentage_bps' => ['required', 'integer', 'min:0', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
