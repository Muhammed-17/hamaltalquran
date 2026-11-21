<?php

namespace App\Services\Badges;

use App\Models\Badges\BadgeLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class BadgeLogService
{
    public function log(
        string $action,
        Model|string $entity,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?User $user = null,
    ): BadgeLog {
        return BadgeLog::create([
            'user_id'     => $user?->id ?? auth()->id(),
            'action'      => $action,
            'entity_type' => is_string($entity) ? $entity : $entity->getTable(),
            'entity_id'   => $entityId ?? (is_string($entity) ? null : $entity->getKey()),
            'old_values'  => $oldValues,
            'new_values'  => $newValues,
            'reason'      => $reason,
            'created_at'  => now(),
        ]);
    }
}
