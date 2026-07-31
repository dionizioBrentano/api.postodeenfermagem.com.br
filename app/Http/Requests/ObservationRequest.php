<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ObservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'type' => ['required', 'in:vital-signs,evolution,other'],
            'recorded_at' => ['required', 'date'],
            'content' => ['required'], // String or Array/JSON
        ];

        return $rules;
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->type === 'vital-signs') {
                if (!is_array($this->content)) {
                    $validator->errors()->add('content', 'O conteúdo de sinais vitais deve ser um objeto JSON estruturado.');
                }
            } elseif ($this->type === 'evolution' || $this->type === 'other') {
                if (!is_string($this->content)) {
                    $validator->errors()->add('content', 'O conteúdo da evolução deve ser texto livre.');
                }
            }
        });
    }

    protected function prepareForValidation()
    {
        // Se for vital-signs e vier como json payload associativo, já será array.
        // Se for enviado como string json, podemos tentar decodificar (geralmente Laravel já faz isso no body json).
    }
}
