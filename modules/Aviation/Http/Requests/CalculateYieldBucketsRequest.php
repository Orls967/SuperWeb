<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalculateYieldBucketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'flight_id' => ['required', 'integer', 'exists:avi_flights,id'],
            'fare_classes' => ['nullable', 'array', 'min:2'],
            'fare_classes.*.code' => ['required_with:fare_classes', 'string', 'max:5'],
            'fare_classes.*.fare' => ['required_with:fare_classes', 'integer', 'min:1000'],
            'fare_classes.*.mean_demand' => ['required_with:fare_classes', 'numeric', 'min:0'],
            'fare_classes.*.std_dev' => ['required_with:fare_classes', 'numeric', 'min:0.1'],
        ];
    }
}
