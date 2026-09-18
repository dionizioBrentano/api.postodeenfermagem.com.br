<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicServiceReviewResource;
use App\Models\ServiceReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Vitrine pública de avaliações e depoimentos de serviços (sem autenticação).
 *
 * Utiliza o middleware "tenant" para isolamento multi-tenant via HasTenant.
 * Apenas avaliações publicadas (com published_at preenchido) são retornadas.
 * Dados sensíveis (e-mail, CPF, telefone) nunca são expostos.
 * Se a avaliação for anônima, o nome é omitido; caso contrário, apenas o primeiro nome é exposto.
 */
class PublicServiceReviewController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'procedure_slug' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = ServiceReview::query()
            ->published()
            ->with(['serviceRequest.procedure', 'clientUser', 'servicePoint']);

        if ($request->filled('procedure_slug')) {
            $slug = $request->query('procedure_slug');
            $query->whereHas('serviceRequest.procedure', function ($q) use ($slug) {
                $q->where('slug', $slug);
            });
        }

        $perPage = min((int) $request->input('per_page', 15), 50);
        if ($perPage <= 0) {
            $perPage = 15;
        }

        $reviews = $query->latest('published_at')
            ->paginate($perPage)
            ->withQueryString();

        return PublicServiceReviewResource::collection($reviews);
    }
}
