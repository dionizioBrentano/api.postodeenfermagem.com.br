<?php

namespace App\Http\Requests;

use App\Rules\Cpf;
use App\Rules\Cns;
use Illuminate\Foundation\Http\FormRequest;

class PatientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Autenticação já é tratada por middlewares
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'cpf' => ['required', 'string', new Cpf],
            'cns' => ['nullable', 'string', new Cns],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Limpar pontuação antes de validar se necessário, mas as regras já tratam isso internamente.
        // É melhor limpar aqui para que o banco receba os dados apenas com números se desejarmos.
        if ($this->has('cpf')) {
            $this->merge([
                'cpf' => preg_replace('/[^0-9]/', '', $this->cpf),
            ]);
        }
        
        if ($this->has('cns')) {
            $this->merge([
                'cns' => preg_replace('/[^0-9]/', '', $this->cns),
            ]);
        }
    }
}
