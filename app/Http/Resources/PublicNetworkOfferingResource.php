<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class PublicNetworkOfferingResource extends PublicOfferingResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        $data['tenant_id'] = $this->tenant_id;
        $data['tenant_name'] = $this->tenant?->name;
        $data['tenant_slug'] = $this->tenant?->slug;

        return $data;
    }
}
