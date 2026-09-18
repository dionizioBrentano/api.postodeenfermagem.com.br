<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicNetworkOfferingResource;
use App\Models\Offering;
use App\Models\ServicePoint;
use App\Models\Tenant;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicNetworkOfferingController extends Controller
{
    /**
     * Busca pública de ofertas de procedimentos em toda a rede de atendimento.
     */
    public function search(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $request->validate([
            'procedure_slug' => ['required', 'string'],
            'cep' => ['nullable', 'string', 'max:20'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $refLat = $request->filled('lat') ? (float) $request->lat : ($request->filled('latitude') ? (float) $request->latitude : null);
        $refLng = $request->filled('lng') ? (float) $request->lng : ($request->filled('longitude') ? (float) $request->longitude : null);

        if ($request->hasHeader('X-Tenant-ID')) {
            $tenantId = $request->header('X-Tenant-ID');
            $tenant = Tenant::find($tenantId);

            if (! $tenant) {
                return response()->json([
                    'message' => 'Tenant não encontrado.',
                ], 403);
            }

            if ($tenant->status !== 'active') {
                return response()->json([
                    'message' => 'Tenant inativo ou suspenso.',
                ], 403);
            }

            // O header X-Tenant-ID é usado estritamente para auditoria; a busca é em toda a rede.
            AuditService::log('network_offerings_search', $tenant, null, [
                'procedure_slug' => $request->procedure_slug,
                'cep' => $request->cep,
                'lat' => $refLat,
                'lng' => $refLng,
            ]);
        }

        $offerings = Offering::withoutGlobalScope('tenant')
            ->active()
            ->whereHas('procedure', function ($q) use ($request) {
                $q->withoutGlobalScope('tenant')
                    ->published()
                    ->where('slug', $request->procedure_slug);
            })
            ->whereHas('servicePoint', function ($q) {
                $q->withoutGlobalScope('tenant')
                    ->active();
            })
            ->whereHas('tenant', function ($q) {
                $q->where('status', 'active');
            })
            ->with([
                'servicePoint' => fn ($q) => $q->withoutGlobalScope('tenant'),
                'procedure' => fn ($q) => $q->withoutGlobalScope('tenant'),
                'tenant',
            ])
            ->get();

        if (($refLat === null || $refLng === null) && $request->filled('cep')) {
            $cleanCep = preg_replace('/[^0-9]/', '', $request->cep);
            $referencePoint = ServicePoint::withoutGlobalScope('tenant')
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->where(function ($q) use ($request, $cleanCep) {
                    $q->where('cep', $request->cep);
                    if ($cleanCep !== '') {
                        $q->orWhereRaw("REPLACE(cep, '-', '') = ?", [$cleanCep]);
                    }
                })
                ->first();

            if ($referencePoint) {
                $refLat = (float) $referencePoint->latitude;
                $refLng = (float) $referencePoint->longitude;
            }
        }

        $hasCoordinates = ($refLat !== null && $refLng !== null);

        if ($hasCoordinates) {
            $sorted = $offerings->sort(function (Offering $a, Offering $b) use ($refLat, $refLng) {
                $spA = $a->servicePoint;
                $spB = $b->servicePoint;

                $hasCoordA = ($spA && $spA->latitude !== null && $spA->longitude !== null);
                $hasCoordB = ($spB && $spB->latitude !== null && $spB->longitude !== null);

                if ($hasCoordA && $hasCoordB) {
                    $distA = $this->calculateDistance($refLat, $refLng, (float) $spA->latitude, (float) $spA->longitude);
                    $distB = $this->calculateDistance($refLat, $refLng, (float) $spB->latitude, (float) $spB->longitude);

                    if (abs($distA - $distB) > 0.00001) {
                        return $distA <=> $distB;
                    }
                } elseif ($hasCoordA && ! $hasCoordB) {
                    return -1;
                } elseif (! $hasCoordA && $hasCoordB) {
                    return 1;
                }

                $scoreA = (float) ($spA?->quality_score ?? 0);
                $scoreB = (float) ($spB?->quality_score ?? 0);

                if ($scoreA !== $scoreB) {
                    return $scoreB <=> $scoreA; // Descending
                }

                $nameA = (string) ($spA?->name ?? '');
                $nameB = (string) ($spB?->name ?? '');

                return strcasecmp($nameA, $nameB); // Ascending
            })->values();
        } else {
            $sorted = $offerings->sort(function (Offering $a, Offering $b) {
                $scoreA = (float) ($a->servicePoint?->quality_score ?? 0);
                $scoreB = (float) ($b->servicePoint?->quality_score ?? 0);

                if ($scoreA !== $scoreB) {
                    return $scoreB <=> $scoreA; // Descending
                }

                $nameA = (string) ($a->servicePoint?->name ?? '');
                $nameB = (string) ($b->servicePoint?->name ?? '');

                return strcasecmp($nameA, $nameB); // Ascending
            })->values();
        }

        return PublicNetworkOfferingResource::collection($sorted);
    }

    /**
     * Calcula distância aproximada em km entre dois pontos geográficos (Fórmula de Haversine).
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // Raio da Terra em km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
