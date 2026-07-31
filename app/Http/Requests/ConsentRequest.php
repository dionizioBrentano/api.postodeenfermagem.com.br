<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purposes' => ['required', 'array'],
            'purposes.*' => ['string'],
            'data_categories' => ['required', 'array'],
            'data_categories.*' => ['string'],
            'valid_until' => ['nullable', 'date', 'after:today'],
        ];
    }
}
