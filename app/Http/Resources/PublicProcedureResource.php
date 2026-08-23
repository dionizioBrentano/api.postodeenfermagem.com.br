<?php

namespace App\Http\Resources;

use App\Models\Procedure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representação pública de um Procedimento de Enfermagem (rotas sem
 * autenticação). Expõe apenas o conteúdo editorial — nada de autoria,
 * tenant_id, status interno ou datas de manutenção do registro.
 *
 * @mixin \App\Models\Procedure
 */
class PublicProcedureResource extends JsonResource
{
    /**
     * Quando true, o corpo completo do procedimento é omitido — usado pela
     * subclasse PublicProcedureListResource nas listagens.
     */
    protected bool $summaryOnly = false;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_filter([
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'category' => $this->category,
            'category_label' => Procedure::CATEGORY_LABELS[$this->category] ?? $this->category,
            'short_description' => $this->short_description,
            'content' => $this->summaryOnly ? null : $this->content,
            'featured_image' => $this->featured_image,
            'gallery' => $this->summaryOnly ? null : ($this->gallery ?? []),
            'order' => $this->order,
            'meta_title' => $this->meta_title ?: $this->title,
            'meta_description' => $this->meta_description ?: $this->short_description,
            'published_at' => $this->published_at?->toIso8601String(),
        ], fn ($value) => $value !== null);
    }
}
