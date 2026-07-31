<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMedicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'medication_details' => ['required', 'string'],
            'status' => ['nullable', 'in:active,completed,cancelled,stopped'],
        ];
    }
}
