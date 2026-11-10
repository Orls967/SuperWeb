<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi Permintaan Pendaftaran Skema Event Baru (SpineSchemaRegisterRequest)
 */
class SpineSchemaRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'topic' => ['required', 'string', 'min:5', 'max:150', 'regex:/^[a-z0-9_\.]+[a-z0-9_]$/'],
            'version' => ['required', 'integer', 'min:1'],
            'schema_definition' => ['required', 'array'],
            'schema_definition.type' => ['required', 'string', 'in:object'],
            'schema_definition.properties' => ['required', 'array'],
            'schema_definition.required' => ['nullable', 'array'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'topic.required' => 'Nama topik event wajib diisi.',
            'topic.regex' => 'Format topik harus berupa segmen berhuruf kecil yang dipisahkan titik (misal: order.created).',
            'version.required' => 'Nomor versi skema wajib diisi.',
            'version.integer' => 'Nomor versi skema harus berupa bilangan bulat positif.',
            'schema_definition.required' => 'Definisi JSON schema wajib disertakan dalam format array/objek.',
            'schema_definition.type.in' => 'Root JSON schema harus memiliki type berupa "object".',
            'schema_definition.properties.required' => 'Atribut "properties" wajib ada dalam definisi schema.',
        ];
    }
}
