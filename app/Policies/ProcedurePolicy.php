<?php

namespace App\Policies;

use App\Models\Procedure;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Regras de acesso aos Procedimentos de Enfermagem.
 *
 * Leitura: qualquer usuário autenticado do tenant (a listagem pública, sem
 * autenticação, não passa por aqui — o controller público filtra por
 * scopePublished()).
 *
 * Escrita: apenas administradores do tenant.
 */
class ProcedurePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Procedure $procedure): bool
    {
        return $this->sameTenant($user, $procedure);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, Procedure $procedure): bool
    {
        return $this->isAdmin($user) && $this->sameTenant($user, $procedure);
    }

    public function delete(User $user, Procedure $procedure): bool
    {
        return $this->update($user, $procedure);
    }

    public function restore(User $user, Procedure $procedure): bool
    {
        return $this->update($user, $procedure);
    }

    public function forceDelete(User $user, Procedure $procedure): bool
    {
        return $this->update($user, $procedure);
    }

    /**
     * Administrador do tenant (ou super admin global, sem tenant_id).
     */
    protected function isAdmin(User $user): bool
    {
        return $user->user_type === 'admin';
    }

    /**
     * Reforço do isolamento multi-tenant. O global scope da trait HasTenant
     * já impede que um registro de outro tenant sequer seja carregado; esta
     * checagem é a segunda camada, para o caso de o registro chegar aqui por
     * outro caminho (job, console, consulta sem scope).
     *
     * O super admin global (tenant_id nulo) opera dentro do tenant indicado
     * pelo header X-Tenant-ID, então não é barrado aqui.
     */
    protected function sameTenant(User $user, Procedure $procedure): bool
    {
        if ($user->tenant_id === null) {
            return $this->isAdmin($user);
        }

        return $user->tenant_id === $procedure->tenant_id;
    }
}
