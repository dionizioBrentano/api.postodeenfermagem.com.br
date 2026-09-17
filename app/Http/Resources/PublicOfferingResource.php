<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicOfferingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'offering_id' => $this->id,
            'service_point_id' => $this->service_point_id,
            'name' => $this->servicePoint?->name,
            'quality_score' => $this->servicePoint?->quality_score !== null ? (float) $this->servicePoint->quality_score : null,
            'cep' => $this->servicePoint?->cep,
            'coverage_km' => $this->servicePoint?->coverage_km !== null ? (float) $this->servicePoint->coverage_km : null,
            'procedure_slug' => $this->procedure?->slug,
            'procedure_title' => $this->procedure?->title,
            'latitude' => $this->servicePoint?->latitude !== null ? (float) $this->servicePoint->latitude : null,
            'longitude' => $this->servicePoint?->longitude !== null ? (float) $this->servicePoint->longitude : null,
        ];
    }
}
