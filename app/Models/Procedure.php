<?php

namespace App\Models;

use App\Services\HtmlSanitizer;
use App\Traits\Auditable;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Procedimento de Enfermagem — conteúdo editorial mantido por
 * administradores do tenant e exposto publicamente quando publicado.
 *
 * Não guarda dado clínico de paciente, por isso não usa a criptografia
 * por registro (HasEncryptedFields); mantém, porém, isolamento por tenant
 * (HasTenant), auditoria (Auditable) e soft delete.
 */
class Procedure extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasTenant, Auditable;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    /**
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_ARCHIVED,
    ];

    public const CATEGORY_APLICACAO_MEDICAMENTOS = 'aplicacao_medicamentos';
    public const CATEGORY_CURATIVOS_FERIDAS = 'curativos_feridas';
    public const CATEGORY_ELIMINACOES = 'eliminacoes';
    public const CATEGORY_VIAS_AEREAS = 'vias_aereas';
    public const CATEGORY_SONDAS_ALIMENTARES = 'sondas_alimentares';
    public const CATEGORY_OUTROS = 'outros';

    /**
     * @var array<int, string>
     */
    public const CATEGORIES = [
        self::CATEGORY_APLICACAO_MEDICAMENTOS,
        self::CATEGORY_CURATIVOS_FERIDAS,
        self::CATEGORY_ELIMINACOES,
        self::CATEGORY_VIAS_AEREAS,
        self::CATEGORY_SONDAS_ALIMENTARES,
        self::CATEGORY_OUTROS,
    ];

    /**
     * Rótulos legíveis das categorias (usados nas API Resources).
     *
     * @var array<string, string>
     */
    public const CATEGORY_LABELS = [
        self::CATEGORY_APLICACAO_MEDICAMENTOS => 'Aplicação de Medicamentos',
        self::CATEGORY_CURATIVOS_FERIDAS => 'Curativos e Tratamento de Feridas',
        self::CATEGORY_ELIMINACOES => 'Eliminações (Sondas e Enemas)',
        self::CATEGORY_VIAS_AEREAS => 'Cuidados com Vias Aéreas',
        self::CATEGORY_SONDAS_ALIMENTARES => 'Sondas Alimentares',
        self::CATEGORY_OUTROS => 'Outros',
    ];

    protected $fillable = [
        'tenant_id',
        'title',
        'slug',
        'category',
        'short_description',
        'content',
        'featured_image',
        'gallery',
        'order',
        'status',
        'meta_title',
        'meta_description',
        'published_at',
        'created_by',
        'updated_by',
    ];

    /**
     * Defaults aplicados já na instância (e não apenas na coluna), para que
     * a API Resource devolva o estado real logo na resposta de criação.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'order' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $procedure) {
            $procedure->slug = $procedure->resolveSlug();
            $procedure->created_by ??= auth()->id();
        });

        static::updating(function (self $procedure) {
            // Só recalcula o slug se ele foi explicitamente alterado; o slug
            // de um procedimento já publicado é uma URL pública e não deve
            // mudar sozinho quando o título é corrigido.
            if ($procedure->isDirty('slug')) {
                $procedure->slug = $procedure->resolveSlug();
            }

            $procedure->updated_by = auth()->id() ?? $procedure->updated_by;
        });
    }

    // ==========================================
    // SLUG
    // ==========================================

    /**
     * Gera um slug único dentro do tenant, a partir do slug informado ou,
     * na ausência dele, do título.
     */
    protected function resolveSlug(): string
    {
        $base = Str::slug($this->slug ?: $this->title);

        if ($base === '') {
            $base = 'procedimento';
        }

        $base = Str::limit($base, 200, '');
        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($slug)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * Verifica colisão de slug dentro do tenant do próprio registro,
     * incluindo registros removidos (soft delete) — o índice único do banco
     * também os considera.
     */
    protected function slugExists(string $slug): bool
    {
        return static::withoutGlobalScope('tenant')
            ->withTrashed()
            ->where('tenant_id', $this->tenant_id)
            ->where('slug', $slug)
            ->when($this->exists, fn (Builder $query) => $query->whereKeyNot($this->getKey()))
            ->exists();
    }

    // ==========================================
    // SANITIZAÇÃO DO CONTEÚDO EDITORIAL
    // ==========================================

    public function setContentAttribute(?string $value): void
    {
        $this->attributes['content'] = HtmlSanitizer::clean($value);
    }

    public function setShortDescriptionAttribute(?string $value): void
    {
        $this->attributes['short_description'] = HtmlSanitizer::toPlainText($value);
    }

    public function setMetaDescriptionAttribute(?string $value): void
    {
        $this->attributes['meta_description'] = HtmlSanitizer::toPlainText($value);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    /**
     * Apenas procedimentos visíveis publicamente.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->where(function (Builder $query) {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function scopeByCategory(Builder $query, ?string $category): Builder
    {
        return $query->when(
            $category !== null && $category !== '',
            fn (Builder $query) => $query->where('category', $category)
        );
    }

    public function scopeByStatus(Builder $query, ?string $status): Builder
    {
        return $query->when(
            $status !== null && $status !== '',
            fn (Builder $query) => $query->where('status', $status)
        );
    }

    /**
     * Busca textual simples em título, resumo e slug.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($term)).'%';

        return $query->where(function (Builder $query) use ($like) {
            $query->where('title', 'like', $like)
                ->orWhere('short_description', 'like', $like)
                ->orWhere('slug', 'like', $like);
        });
    }

    /**
     * Ordenação editorial padrão das listagens.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order')->orderBy('title');
    }

    // ==========================================
    // ESTADO
    // ==========================================

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && ($this->published_at === null || $this->published_at->lessThanOrEqualTo(now()));
    }

    public function publish(): bool
    {
        return $this->forceFill([
            'status' => self::STATUS_PUBLISHED,
            'published_at' => $this->published_at ?? now(),
        ])->save();
    }

    public function unpublish(): bool
    {
        return $this->forceFill([
            'status' => self::STATUS_DRAFT,
            'published_at' => null,
        ])->save();
    }

    // ==========================================
    // RELACIONAMENTOS
    // ==========================================

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
