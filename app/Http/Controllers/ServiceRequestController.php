<?php

namespace App\Http\Controllers;

use App\Http\Resources\ServiceRequestResource;
use App\Models\Offering;
use App\Models\Procedure;
use App\Models\ServicePoint;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ServiceRequestController extends Controller
{
    /**
     * Lista pedidos de serviço.
     * Cliente (patient): apenas os seus pedidos.
     * Professional/Admin: todos os pedidos do tenant.
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $user = $request->user();

        // Se paciente tentar consultar os pedidos de outro cliente
        if ($user->user_type === 'patient' && $request->filled('client_user_id') && $request->client_user_id !== $user->id) {
            return response()->json([
                'message' => 'Acesso não autorizado aos pedidos de outro cliente.',
                'code' => 'forbidden',
            ], 403);
        }

        $query = ServiceRequest::query()
            ->with(['procedure', 'servicePoint', 'offering']);

        if ($user->user_type === 'patient') {
            $query->where('client_user_id', $user->id);
        } elseif ($request->filled('client_user_id')) {
            $query->where('client_user_id', $request->client_user_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->latest()->paginate($request->input('per_page', 15))->withQueryString();

        return ServiceRequestResource::collection($requests);
    }

    /**
     * Exibe os detalhes de um pedido.
     */
    public function show(Request $request, string $id): ServiceRequestResource|JsonResponse
    {
        $user = $request->user();

        $serviceRequest = ServiceRequest::query()
            ->with(['procedure', 'servicePoint', 'offering'])
            ->findOrFail($id);

        if ($user->user_type === 'patient' && $serviceRequest->client_user_id !== $user->id) {
            return response()->json([
                'message' => 'Acesso não autorizado aos pedidos de outro cliente.',
                'code' => 'forbidden',
            ], 403);
        }

        return new ServiceRequestResource($serviceRequest);
    }

    /**
     * Cria um novo pedido de serviço (requer MFA step-up).
     * Qualquer usuário do tenant (patient ou professional) atua como client_user_id.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'procedure_slug' => ['required', 'string'],
            'cep_servico' => ['required', 'string', 'max:20'],
            'slot_date' => ['required', 'date'],
            'slot_window' => ['required', 'string', Rule::in(ServiceRequest::SLOT_WINDOWS)],
            'offering_id' => ['nullable', 'string', 'uuid'],
            'service_point_id' => ['nullable', 'string', 'uuid'],
            'notes_cliente' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $procedure = Procedure::query()
            ->published()
            ->where('slug', $request->procedure_slug)
            ->first();

        if (! $procedure) {
            return response()->json([
                'message' => 'Procedimento não encontrado ou não publicado.',
                'errors' => [
                    'procedure_slug' => ['Procedimento não encontrado ou não publicado neste tenant.'],
                ],
            ], 422);
        }

        $offering = null;
        $servicePointId = $request->service_point_id;

        if ($request->filled('offering_id')) {
            $offering = Offering::query()
                ->active()
                ->where('id', $request->offering_id)
                ->first();

            if (! $offering) {
                return response()->json([
                    'message' => 'Oferta não encontrada ou inativa.',
                    'errors' => [
                        'offering_id' => ['Oferta não encontrada ou inativa neste tenant.'],
                    ],
                ], 422);
            }

            $servicePointId = $offering->service_point_id;
        } elseif ($request->filled('service_point_id')) {
            $servicePoint = ServicePoint::query()
                ->active()
                ->where('id', $request->service_point_id)
                ->first();

            if (! $servicePoint) {
                return response()->json([
                    'message' => 'Ponto de atendimento não encontrado ou inativo.',
                    'errors' => [
                        'service_point_id' => ['Ponto de atendimento não encontrado ou inativo neste tenant.'],
                    ],
                ], 422);
            }
        }

        $tenant = app('tenant');
        $user = $request->user();

        $serviceRequest = ServiceRequest::create([
            'tenant_id' => $tenant->id,
            'client_user_id' => $user->id,
            'procedure_id' => $procedure->id,
            'offering_id' => $offering?->id,
            'service_point_id' => $servicePointId,
            'cep_servico' => $request->cep_servico,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'slot_date' => $request->slot_date,
            'slot_window' => $request->slot_window,
            'status' => ServiceRequest::STATUS_REQUESTED,
            'notes_cliente' => $request->notes_cliente,
        ]);

        $serviceRequest->load(['procedure', 'servicePoint', 'offering']);

        return (new ServiceRequestResource($serviceRequest))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Atualiza o status de um pedido de serviço.
     * Só professional/admin do tenant pode mudar para accepted|done|cancelled.
     * Cliente só pode mudar para cancelled se o pedido ainda estiver requested.
     */
    public function update(Request $request, string $id): ServiceRequestResource|JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', Rule::in(['accepted', 'done', 'cancelled'])],
        ]);

        $serviceRequest = ServiceRequest::query()
            ->with(['procedure', 'servicePoint', 'offering'])
            ->findOrFail($id);

        $user = $request->user();
        $newStatus = $request->status;

        $isStaff = in_array($user->user_type, ['professional', 'admin'], true);
        $isOwner = ($serviceRequest->client_user_id === $user->id);

        if (! $isStaff && ! $isOwner) {
            return response()->json([
                'message' => 'Acesso não autorizado para alterar este pedido.',
                'code' => 'forbidden',
            ], 403);
        }

        if (! $isStaff && $isOwner) {
            if ($newStatus !== ServiceRequest::STATUS_CANCELLED) {
                return response()->json([
                    'message' => 'Clientes só têm permissão para cancelar o pedido.',
                    'code' => 'forbidden_status_transition',
                ], 403);
            }

            if ($serviceRequest->status !== ServiceRequest::STATUS_REQUESTED) {
                return response()->json([
                    'message' => 'O pedido só pode ser cancelado se ainda estiver com status requested.',
                    'code' => 'cannot_cancel_non_requested',
                ], 403);
            }
        }

        $serviceRequest->status = $newStatus;
        $serviceRequest->save();

        return new ServiceRequestResource($serviceRequest);
    }
}
