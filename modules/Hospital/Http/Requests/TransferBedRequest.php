<?php

declare(strict_types=1);

namespace Modules\Hospital\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TransferBedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'admission_id' => ['required', 'integer', 'exists:hsp_admissions,id'],
            'target_bed_id' => ['required', 'integer', 'exists:hsp_beds,id'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }
}
