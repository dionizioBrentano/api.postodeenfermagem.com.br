<?php

namespace App\Http\Controllers;

use App\Http\Resources\ServiceReviewResource;
use App\Models\ServicePoint;
use App\Models\ServiceRequest;
use App\Models\ServiceReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceReviewController extends Controller
{
    /**
     * Cadastra avaliação para um pedido de serviço (requer MFA step-up).
     *
     * Regras:
     * - Apenas o client_user_id do pedido pode avaliar.
     * - O pedido precisa estar com status "done".
     * - Prazo máximo de 14 dias a partir de updated_at do done (ou completed_at se houver).
     * - Apenas uma avaliação por pedido (única).
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $serviceRequest = ServiceRequest::findOrFail($id);
        $user = $request->user();

        // Só o client_user_id do pedido
        if ($serviceRequest->client_user_id !== $user->id) {
            return response()->json([
                'message' => 'Acesso não autorizado para avaliar este pedido.',
                'code' => 'forbidden',
            ], 403);
        }

        // Pedido precisa estar com status done
        if ($serviceRequest->status !== ServiceRequest::STATUS_DONE) {
            return response()->json([
                'message' => 'Apenas pedidos concluídos podem ser avaliados.',
                'code' => 'service_request_not_done',
            ], 422);
        }

        // Prazo de 14 dias a partir de completed_at ou updated_at
        $doneAt = $serviceRequest->completed_at ?? $serviceRequest->updated_at;
        if ($doneAt && $doneAt->clone()->addDays(14)->isPast()) {
            return response()->json([
                'message' => 'O prazo de 14 dias para avaliação deste pedido expirou.',
                'code' => 'review_deadline_expired',
            ], 422);
        }

        // Único: um review por pedido
        if ($serviceRequest->review()->exists()) {
            return response()->json([
                'message' => 'Este pedido já possui uma avaliação cadastrada.',
                'code' => 'review_already_exists',
            ], 422);
        }

        $validated = $request->validate([
            'stars' => ['required', 'integer', 'between:1,5'],
            'body' => ['required', 'string', 'max:5000'],
            'anonymous' => ['nullable', 'boolean'],
            'publish_requested' => ['nullable', 'boolean'],
        ]);

        $review = ServiceReview::create([
            'tenant_id' => $serviceRequest->tenant_id,
            'service_request_id' => $serviceRequest->id,
            'client_user_id' => $user->id,
            'service_point_id' => $serviceRequest->service_point_id,
            'stars' => (int) $validated['stars'],
            'body' => $validated['body'],
            'anonymous' => $request->boolean('anonymous', true),
            'publish_requested' => $request->boolean('publish_requested', false),
            'published_at' => null,
            'published_by' => null,
        ]);

        // Não recalcular quality_score ainda (placeholder)
        $this->recalculateQualityScorePlaceholder($serviceRequest->service_point_id);

        return (new ServiceReviewResource($review))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Exibe a avaliação do próprio pedido.
     * Permitido para o cliente dono ou professional/admin do tenant.
     */
    public function showByRequest(Request $request, string $id): ServiceReviewResource|JsonResponse
    {
        $serviceRequest = ServiceRequest::with('review')->findOrFail($id);
        $user = $request->user();

        $isOwner = ($serviceRequest->client_user_id === $user->id);
        $isStaff = in_array($user->user_type, ['professional', 'admin'], true);

        if (! $isOwner && ! $isStaff) {
            return response()->json([
                'message' => 'Acesso não autorizado aos dados deste pedido.',
                'code' => 'forbidden',
            ], 403);
        }

        if (! $serviceRequest->review) {
            return response()->json([
                'message' => 'Avaliação não encontrada para este pedido.',
                'code' => 'not_found',
            ], 404);
        }

        return new ServiceReviewResource($serviceRequest->review);
    }

    /**
     * Exibe a avaliação por ID.
     * Permitido para o cliente dono ou professional/admin do tenant.
     */
    public function show(Request $request, string $id): ServiceReviewResource|JsonResponse
    {
        $review = ServiceReview::findOrFail($id);
        $user = $request->user();

        $isOwner = ($review->client_user_id === $user->id);
        $isStaff = in_array($user->user_type, ['professional', 'admin'], true);

        if (! $isOwner && ! $isStaff) {
            return response()->json([
                'message' => 'Acesso não autorizado a esta avaliação.',
                'code' => 'forbidden',
            ], 403);
        }

        return new ServiceReviewResource($review);
    }

    /**
     * Publica uma avaliação para a vitrine pública.
     *
     * Regras:
     * - Só professional/admin com MFA step-up.
     * - Só se publish_requested for true.
     * - Define published_at e published_by.
     */
    public function publish(Request $request, string $id): ServiceReviewResource|JsonResponse
    {
        $user = $request->user();

        if (! in_array($user->user_type, ['professional', 'admin'], true)) {
            return response()->json([
                'message' => 'Apenas profissionais ou administradores podem publicar avaliações.',
                'code' => 'forbidden',
            ], 403);
        }

        $review = ServiceReview::findOrFail($id);

        if (! $review->publish_requested) {
            return response()->json([
                'message' => 'A avaliação não possui autorização de publicação solicitada pelo cliente.',
                'code' => 'publish_not_requested',
            ], 422);
        }

        $review->published_at = now();
        $review->published_by = $user->id;

        $servicePointId = $review->service_point_id ?? $review->serviceRequest?->service_point_id;
        if ($servicePointId && ! $review->service_point_id) {
            $review->service_point_id = $servicePointId;
        }

        $review->save();

        $this->recalculateQualityScore($servicePointId);

        return new ServiceReviewResource($review);
    }

    /**
     * Recalcula o quality_score do service_point do pedido:
     * média das stars dos reviews published daquele service_point.
     * Decimal 4,2. Se não houver reviews published, null.
     */
    protected function recalculateQualityScore(?string $servicePointId): void
    {
        if (! $servicePointId) {
            return;
        }

        $servicePoint = ServicePoint::withoutGlobalScope('tenant')->find($servicePointId);
        if (! $servicePoint) {
            return;
        }

        $avg = ServiceReview::withoutGlobalScope('tenant')
            ->where('service_point_id', $servicePointId)
            ->published()
            ->avg('stars');

        $servicePoint->update([
            'quality_score' => $avg !== null ? round((float) $avg, 2) : null,
        ]);
    }

    /**
     * Placeholder mantido para compatibilidade do fluxo de store.
     */
    protected function recalculateQualityScorePlaceholder(?string $servicePointId): void
    {
        //
    }
}
