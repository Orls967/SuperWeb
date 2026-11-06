<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateEsgListingRequest extends FormRequest
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
            'item_type' => ['required', 'string', 'in:CARBON_OFFSET,REC_CERTIFICATE,RECYCLED_MATERIAL,CIRCULAR_CREDIT'],
            'reference_item_id' => ['required', 'integer'],
            'listing_title' => ['required', 'string', 'max:150'],
            'available_quantity' => ['required', 'integer', 'min:1'],
            'price_idr_per_unit' => ['required', 'integer', 'min:1000'],
        ];
    }
}
