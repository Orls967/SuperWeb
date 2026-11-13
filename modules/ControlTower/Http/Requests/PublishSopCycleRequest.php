<?php

declare(strict_types=1);

namespace Modules\ControlTower\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\ControlTower\Domain\StateMachines\SopCycleStateMachine;

final class PublishSopCycleRequest extends FormRequest
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
            'action' => ['nullable', 'string', 'in:advance,rollback,submit_approval,publish,close'],
            'to_status' => ['nullable', 'required_without:action', 'string', Rule::in(SopCycleStateMachine::states())],
            'reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'demand_plan_qty' => ['nullable', 'integer', 'min:0'],
            'supply_plan_qty' => ['nullable', 'integer', 'min:0'],
            'capacity_utilization_bps' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'gross_margin_est_idr' => ['nullable', 'integer'],
            'cash_impact_est_idr' => ['nullable', 'integer'],
            'consensus_snapshot' => ['nullable', 'array'],
        ];
    }
}
