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
        return [
            'type' => ['required', 'in:vital-signs,evolution,other'],
            'recorded_at' => ['required', 'date'],
            'content' => ['required'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $content = $this->input('content');

        // stdClass (JSON object) → array
        if (is_object($content)) {
            $content = json_decode(json_encode($content), true);
        }

        // string JSON → array (vital-signs) ou mantém string (evolution)
        if (is_string($content)) {
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $content = $decoded;
            }
        }

        if ($content !== $this->input('content')) {
            $this->merge(['content' => $content]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('type');
            $content = $this->input('content');

            if ($type === 'vital-signs') {
                if (! is_array($content)) {
                    $validator->errors()->add('content', 'O conteúdo de sinais vitais deve ser um objeto JSON estruturado.');
                }
            } elseif (in_array($type, ['evolution', 'other'], true)) {
                if (! is_string($content)) {
                    $validator->errors()->add('content', 'O conteúdo da evolução deve ser texto livre.');
                }
            }
        });
    }
}
