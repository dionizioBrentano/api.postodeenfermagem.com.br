<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProcedureRequest;
use App\Http\Requests\UpdateProcedureRequest;
use App\Http\Resources\ProcedureResource;
use App\Models\Procedure;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Gestão dos Procedimentos de Enfermagem (área autenticada).
 *
 * Leitura liberada para qualquer usuário autenticado do tenant; escrita
 * restrita a administradores (ProcedurePolicy + ability tenant:admin nas
 * rotas).
 *
 * Os registros são localizados por findOrFail() dentro da action — e não
 * por route model binding implícito — porque o SubstituteBindings roda
 * antes do middleware "tenant". Resolver o model aqui garante que o global
 * scope da trait HasTenant já esteja ativo, e um id de outro tenant
 * devolve 404 em vez de vazar a existência do registro.
 */
class ProcedureController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Procedure::class);

        $filters = $request->validate([
            'category' => ['nullable', 'string', Rule::in(Procedure::CATEGORIES)],
            'status' => ['nullable', 'string', Rule::in(Procedure::STATUSES)],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'with_trashed' => ['nullable', 'boolean'],
        ]);

        $query = Procedure::query()
            ->byCategory($filters['category'] ?? null)
            ->byStatus($filters['status'] ?? null)
            ->search($filters['search'] ?? null)
            ->ordered();

        // Só administradores podem enxergar a lixeira.
        if (($filters['with_trashed'] ?? false) && Gate::allows('create', Procedure::class)) {
            $query->withTrashed();
        }

        $procedures = $query->paginate($filters['per_page'] ?? 15)->withQueryString();

        return ProcedureResource::collection($procedures);
    }

    public function store(StoreProcedureRequest $request): JsonResponse
    {
        $data = $request->validated();

        // published_at só faz sentido para conteúdo publicado; publicar pelo
        // store sem data explícita carimba o momento atual.
        if (($data['status'] ?? Procedure::STATUS_DRAFT) === Procedure::STATUS_PUBLISHED) {
            $data['published_at'] ??= now();
        }

        $procedure = Procedure::create($data);

        return (new ProcedureResource($procedure))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): ProcedureResource
    {
        $procedure = Procedure::findOrFail($id);

        Gate::authorize('view', $procedure);

        AuditService::log('accessed', $procedure);

        return new ProcedureResource($procedure->load(['creator', 'editor']));
    }

    public function update(UpdateProcedureRequest $request, string $id): ProcedureResource
    {
        // Já resolvido e autorizado pelo Form Request (mesma instância).
        $procedure = $request->procedure();

        $data = $request->validated();

        if (($data['status'] ?? null) === Procedure::STATUS_PUBLISHED) {
            $data['published_at'] ??= $procedure->published_at ?? now();
        }

        $procedure->update($data);

        return new ProcedureResource($procedure->refresh());
    }

    public function destroy(string $id): JsonResponse
    {
        $procedure = Procedure::findOrFail($id);

        Gate::authorize('delete', $procedure);

        $procedure->delete();

        return response()->json([
            'message' => 'Procedimento removido com sucesso.',
        ]);
    }

    /**
     * Publica o procedimento (status = published + carimbo de publicação).
     */
    public function publish(string $id): ProcedureResource
    {
        $procedure = Procedure::findOrFail($id);

        Gate::authorize('update', $procedure);

        $procedure->publish();

        return new ProcedureResource($procedure->refresh());
    }

    /**
     * Retira o procedimento do ar, voltando-o para rascunho.
     */
    public function unpublish(string $id): ProcedureResource
    {
        $procedure = Procedure::findOrFail($id);

        Gate::authorize('update', $procedure);

        $procedure->unpublish();

        return new ProcedureResource($procedure->refresh());
    }

    /**
     * Restaura um procedimento removido (soft delete).
     */
    public function restore(string $id): ProcedureResource
    {
        $procedure = Procedure::withTrashed()->findOrFail($id);

        Gate::authorize('restore', $procedure);

        $procedure->restore();

        return new ProcedureResource($procedure->refresh());
    }
}
