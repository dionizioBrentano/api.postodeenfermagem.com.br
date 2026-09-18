<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicServiceReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $clientName = null;
        if (! $this->anonymous && $this->clientUser) {
            $rawName = trim((string) $this->clientUser->name);
            $parts = preg_split('/\s+/', $rawName);
            $clientName = ! empty($parts[0]) ? $parts[0] : null;
        }

        return [
            'id' => $this->id,
            'stars' => (int) $this->stars,
            'body' => $this->body,
            'anonymous' => (bool) $this->anonymous,
            'client_name' => $clientName,
            'published_at' => $this->published_at?->toIso8601String(),
            'procedure' => $this->when($this->serviceRequest?->procedure, fn () => [
                'id' => $this->serviceRequest->procedure->id,
                'title' => $this->serviceRequest->procedure->title,
                'slug' => $this->serviceRequest->procedure->slug,
            ]),
            'service_point' => $this->when($this->servicePoint, fn () => [
                'id' => $this->servicePoint->id,
                'name' => $this->servicePoint->name,
            ]),
        ];
    }
}
