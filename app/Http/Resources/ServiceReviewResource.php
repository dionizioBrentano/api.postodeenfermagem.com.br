<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceReviewResource extends JsonResource
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
            'service_request_id' => $this->service_request_id,
            'client_user_id' => $this->client_user_id,
            'service_point_id' => $this->service_point_id,
            'stars' => (int) $this->stars,
            'body' => $this->body,
            'anonymous' => (bool) $this->anonymous,
            'publish_requested' => (bool) $this->publish_requested,
            'published_at' => $this->published_at?->toIso8601String(),
            'published_by' => $this->published_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
