<?php

namespace App\Http\Resources;

use App\Models\Procedure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representação administrativa de um Procedimento de Enfermagem
 * (rotas autenticadas). Inclui metadados editoriais internos.
 *
 * @mixin \App\Models\Procedure
 */
class ProcedureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'category' => $this->category,
            'category_label' => Procedure::CATEGORY_LABELS[$this->category] ?? $this->category,
            'short_description' => $this->short_description,
            'content' => $this->content,
            'featured_image' => $this->featured_image,
            'gallery' => $this->gallery ?? [],
            'order' => $this->order,
            'status' => $this->status,
            'is_published' => $this->isPublished(),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
            ]),
            'editor' => $this->whenLoaded('editor', fn () => [
                'id' => $this->editor?->id,
                'name' => $this->editor?->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
