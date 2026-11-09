<?php

declare(strict_types=1);

namespace Modules\Hospital\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AcknowledgeCodeBlueRequest extends FormRequest
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
            'event_id' => ['required', 'integer', 'exists:hsp_code_blue_events,id'],
            'responder_role' => ['required', 'string', 'in:nurse,doctor,code_blue_team'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
