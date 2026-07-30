<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    /**
     * Registra um evento de auditoria
     * 
     * @param string $action Ex: created, updated, deleted, login, logout, accessed
     * @param Model|null $auditable O model que sofreu a acao (para amarrar a DEK cascade)
     * @param array|null $oldValues Valores antigos
     * @param array|null $newValues Novos valores
     */
    public static function log(string $action, ?Model $auditable = null, ?array $oldValues = null, ?array $newValues = null): void
    {
        $userId = auth()->id();
        $tenantId = null;

        if ($auditable && isset($auditable->tenant_id)) {
            $tenantId = $auditable->tenant_id;
        } elseif (auth()->check() && isset(auth()->user()->tenant_id)) {
            $tenantId = auth()->user()->tenant_id;
        }

        // Serializa para JSON para o Cast criptografar
        $oldJson = $oldValues ? json_encode($oldValues) : null;
        $newJson = $newValues ? json_encode($newValues) : null;

        AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'action' => $action,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable ? $auditable->getKey() : null,
            'old_values' => $oldJson,
            'new_values' => $newJson,
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
