<?php

declare(strict_types=1);

namespace Modules\Hospital\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddFolioItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'billing_episode_id' => ['required', 'integer', 'exists:hsp_billing_episodes,id'],
            'item_category' => ['required', 'string', 'in:BED_DAY,PROCEDURE,PHARMACY,LAB,RADIOLOGY,CONSUMABLES'],
            'description' => ['required', 'string', 'max:128'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price_idr' => ['required', 'integer', 'min:0'],
        ];
    }
}
