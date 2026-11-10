<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DrawCreditFacilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'current_der' => ['required'], // can be integer permille or float from legacy, converted cleanly
            'description' => ['sometimes', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Nominal penarikan fasilitas wajib diisi.',
            'amount.min' => 'Nominal penarikan fasilitas minimal 1.',
            'current_der.required' => 'Rasio Debt-to-Equity (DER) saat ini wajib disertakan untuk validasi covenant.',
        ];
    }

    public function getAmount(): int
    {
        return (int) $this->input('amount');
    }

    public function getCurrentDerPermille(): int
    {
        $raw = $this->input('current_der');
        if (is_numeric($raw) && (float) $raw <= 50.0 && str_contains((string) $raw, '.')) {
            // legacy float representation like 2.5 => 2500 permille
            return (int) round(((float) $raw) * 1000);
        }

        return (int) $raw;
    }

    public function getDescription(): string
    {
        return (string) $this->input('description', 'Penarikan fasilitas kredit bank');
    }
}
