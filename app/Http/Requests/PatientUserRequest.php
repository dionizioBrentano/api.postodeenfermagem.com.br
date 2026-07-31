<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PatientUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'ativo' => ['boolean'],
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['nullable', 'date', 'after_or_equal:data_inicio'],
            'tipo_vinculo' => ['required', 'string', 'max:255'],
        ];
    }
}
