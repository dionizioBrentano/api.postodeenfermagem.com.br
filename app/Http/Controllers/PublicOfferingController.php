<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicOfferingResource;
use App\Models\Offering;
use App\Models\Procedure;
use App\Models\ServicePoint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicOfferingController extends Controller
{
    /**
     * Busca pública de ofertas de procedimentos nos pontos de atendimento do tenant.
     */
    public function search(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'procedure_slug' => ['required', 'string'],
            'cep' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $procedure = Procedure::query()
            ->published()
            ->where('slug', $request->procedure_slug)
            ->first();

        if (! $procedure) {
            return PublicOfferingResource::collection(collect());
        }

        $offerings = Offering::query()
            ->active()
            ->where('procedure_id', $procedure->id)
            ->whereHas('servicePoint', fn ($q) => $q->active())
            ->with(['servicePoint', 'procedure'])
            ->get();

        $refLat = $request->filled('latitude') ? (float) $request->latitude : null;
        $refLng = $request->filled('longitude') ? (float) $request->longitude : null;

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
            $sorted = $offerings->sortBy(function (Offering $offering) use ($refLat, $refLng) {
                $sp = $offering->servicePoint;
                if ($sp && $sp->latitude !== null && $sp->longitude !== null) {
                    return $this->calculateDistance($refLat, $refLng, (float) $sp->latitude, (float) $sp->longitude);
                }
                return PHP_FLOAT_MAX;
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

        return PublicOfferingResource::collection($sorted);
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
