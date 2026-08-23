<?php

namespace App\Http\Requests;

use App\Models\Procedure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProcedureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Procedure::class) ?? false;
    }

    public function rules(): array
    {
        $tenantId = app()->has('tenant') ? app('tenant')->id : null;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:200',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                // Unicidade por tenant, considerando também os removidos
                // (soft delete), como faz o índice único da tabela.
                Rule::unique('procedures', 'slug')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
            'category' => ['required', 'string', Rule::in(Procedure::CATEGORIES)],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'content' => ['required', 'string', 'max:200000'],
            'featured_image' => ['nullable', 'string', 'max:2048'],
            'gallery' => ['nullable', 'array', 'max:50'],
            'gallery.*' => ['required', 'string', 'max:2048'],
            'order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'status' => ['nullable', 'string', Rule::in(Procedure::STATUSES)],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'O slug deve conter apenas letras minúsculas, números e hífens.',
            'slug.unique' => 'Já existe um procedimento com este slug nesta instituição.',
            'category.in' => 'Categoria inválida.',
            'status.in' => 'Status inválido.',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'título',
            'slug' => 'slug',
            'category' => 'categoria',
            'short_description' => 'resumo',
            'content' => 'conteúdo',
            'featured_image' => 'imagem de destaque',
            'gallery' => 'galeria',
            'order' => 'ordem',
            'status' => 'status',
            'meta_title' => 'meta title',
            'meta_description' => 'meta description',
            'published_at' => 'data de publicação',
        ];
    }
}
