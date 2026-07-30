<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Registra um evento de auditoria.
     *
     * @param string $action Ex: created, updated, deleted, login, logout, accessed
     * @param Model|null $auditable O model que sofreu a ação
     * @param array|null $oldValues Valores antigos
     * @param array|null $newValues Novos valores
     * @param User|null $actingUser Usuário que praticou a ação. Se omitido, usa auth()->user()
     *                              (necessário em endpoints como login, onde o request ainda
     *                              não está autenticado via Sanctum no momento do log).
     */
    public static function log(string $action, ?Model $auditable = null, ?array $oldValues = null, ?array $newValues = null, ?User $actingUser = null): void
    {
        $actingUser ??= auth()->check() ? auth()->user() : null;

        $tenantId = null;

        if ($auditable && isset($auditable->tenant_id)) {
            $tenantId = $auditable->tenant_id;
        } elseif ($actingUser && isset($actingUser->tenant_id)) {
            $tenantId = $actingUser->tenant_id;
        }

        // Serializa para JSON para o Cast criptografar
        $oldJson = $oldValues ? json_encode($oldValues) : null;
        $newJson = $newValues ? json_encode($newValues) : null;

        AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $actingUser?->getKey(),
            'action' => $action,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable ? $auditable->getKey() : null,
            'old_values' => $oldJson,
            'new_values' => $newJson,
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    /**
     * Atalho para registrar um evento explicitamente em nome de um usuário
     * (ex.: login, antes do token Sanctum existir no request).
     */
    public static function logAs(User $actingUser, string $action, ?Model $auditable = null, ?array $oldValues = null, ?array $newValues = null): void
    {
        static::log($action, $auditable, $oldValues, $newValues, $actingUser);
    }
}
