<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicProcedureListResource;
use App\Http\Resources\PublicProcedureResource;
use App\Models\Procedure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Vitrine pública dos Procedimentos de Enfermagem (sem autenticação).
 *
 * Continua exigindo o header X-Tenant-ID (middleware "tenant"), então o
 * global scope da trait HasTenant segue ativo e o isolamento multi-tenant
 * é preservado. Aqui só existe procedimento publicado — scopePublished()
 * é aplicado em todas as consultas.
 */
class PublicProcedureController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'category' => ['nullable', 'string', Rule::in(Procedure::CATEGORIES)],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $procedures = Procedure::query()
            ->published()
            ->byCategory($filters['category'] ?? null)
            ->search($filters['search'] ?? null)
            ->ordered()
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return PublicProcedureListResource::collection($procedures);
    }

    public function show(string $slug): PublicProcedureResource
    {
        $procedure = Procedure::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        return new PublicProcedureResource($procedure);
    }

    /**
     * Categorias disponíveis, com a contagem de procedimentos publicados —
     * usada para montar o menu da vitrine.
     */
    public function categories(): \Illuminate\Http\JsonResponse
    {
        $counts = Procedure::query()
            ->published()
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $categories = collect(Procedure::CATEGORIES)
            ->map(fn (string $category) => [
                'value' => $category,
                'label' => Procedure::CATEGORY_LABELS[$category],
                'total' => (int) ($counts[$category] ?? 0),
            ])
            ->values();

        return response()->json(['data' => $categories]);
    }
}
