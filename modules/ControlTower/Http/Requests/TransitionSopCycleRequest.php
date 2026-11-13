<?php

declare(strict_types=1);

namespace Modules\ControlTower\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TransitionSopCycleRequest extends FormRequest
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
            'to_status' => ['required', 'string', 'in:draft,demand_review,supply_review,pre_sop_balancing,executive_signoff,published,archived'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
