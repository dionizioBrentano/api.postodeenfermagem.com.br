<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'encounter_id' => ['required', 'uuid', 'exists:encounters,id'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['required', 'string'],
            'status' => ['nullable', 'in:active,resolved,inactive'],
        ];
    }
}
