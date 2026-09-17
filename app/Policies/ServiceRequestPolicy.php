<?php

namespace App\Policies;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ServiceRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ServiceRequest $serviceRequest): bool
    {
        if (! $this->sameTenant($user, $serviceRequest)) {
            return false;
        }

        if ($user->user_type === 'admin' || $user->user_type === 'professional') {
            return true;
        }

        return $serviceRequest->client_user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ServiceRequest $serviceRequest): bool
    {
        if (! $this->sameTenant($user, $serviceRequest)) {
            return false;
        }

        if ($user->user_type === 'admin' || $user->user_type === 'professional') {
            return true;
        }

        // Cliente só pode alterar se for o dono e o pedido ainda estiver com status requested
        return $serviceRequest->client_user_id === $user->id && $serviceRequest->status === ServiceRequest::STATUS_REQUESTED;
    }

    protected function sameTenant(User $user, ServiceRequest $serviceRequest): bool
    {
        if ($user->tenant_id === null) {
            return $user->user_type === 'admin';
        }

        return $user->tenant_id === $serviceRequest->tenant_id;
    }
}
