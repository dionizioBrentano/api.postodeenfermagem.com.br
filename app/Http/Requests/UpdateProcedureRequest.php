<?php

namespace App\Http\Requests;

use App\Models\Procedure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProcedureRequest extends FormRequest
{
    private ?Procedure $resolvedProcedure = null;

    /**
     * Resolve o procedimento da rota já sob o global scope de tenant (o
     * middleware "tenant" roda antes da resolução do Form Request), de modo
     * que um id de outro tenant resulte em 404 e não em 403.
     */
    public function procedure(): Procedure
    {
        return $this->resolvedProcedure ??= Procedure::findOrFail($this->route('id'));
    }

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->procedure()) ?? false;
    }

    public function rules(): array
    {
        $procedure = $this->procedure();

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:200',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('procedures', 'slug')
                    ->ignore($procedure->getKey())
                    ->where(fn ($query) => $query->where('tenant_id', $procedure->tenant_id)),
            ],
            'category' => ['sometimes', 'required', 'string', Rule::in(Procedure::CATEGORIES)],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'content' => ['sometimes', 'required', 'string', 'max:200000'],
            'featured_image' => ['nullable', 'string', 'max:2048'],
            'gallery' => ['nullable', 'array', 'max:50'],
            'gallery.*' => ['required', 'string', 'max:2048'],
            'order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'status' => ['sometimes', 'required', 'string', Rule::in(Procedure::STATUSES)],
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
