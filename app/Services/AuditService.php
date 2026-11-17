<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditService
{
    /**
     * Mencatat aktivitas ke evoting.audit_logs (FR-026, BR-020).
     *
     * @param  array<string, mixed>|null  $detail
     */
    public function log(
        string $actorType,
        ?string $actorId,
        string $action,
        ?string $objectType = null,
        ?string $objectId = null,
        ?array $detail = null
    ): AuditLog {
        return AuditLog::create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'object_type' => $objectType,
            'object_id' => $objectId,
            'detail' => $detail,
            'created_at' => now(),
        ]);
    }
}
