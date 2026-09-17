<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceRequestResource extends JsonResource
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
            'tenant_id' => $this->tenant_id,
            'client_user_id' => $this->client_user_id,
            'offering_id' => $this->offering_id,
            'service_point_id' => $this->service_point_id,
            'procedure_id' => $this->procedure_id,
            'procedure' => $this->whenLoaded('procedure', fn () => [
                'id' => $this->procedure->id,
                'title' => $this->procedure->title,
                'slug' => $this->procedure->slug,
            ]),
            'service_point' => $this->whenLoaded('servicePoint', fn () => [
                'id' => $this->servicePoint->id,
                'name' => $this->servicePoint->name,
                'cep' => $this->servicePoint->cep,
            ]),
            'cep_servico' => $this->cep_servico,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'slot_date' => $this->slot_date instanceof \DateTimeInterface ? $this->slot_date->format('Y-m-d') : $this->slot_date,
            'slot_window' => $this->slot_window,
            'status' => $this->status,
            'notes_cliente' => $this->notes_cliente,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
