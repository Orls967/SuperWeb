<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveCapexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'allocated_minor' => ['required', 'integer', 'min:1'],
        ];
    }
}
