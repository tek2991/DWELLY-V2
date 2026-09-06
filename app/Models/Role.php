<?php

namespace App\Models;

use App\Domain\Auth\Enums\RoleName;
use DomainException;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'display_name',
        'description',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::updating(function (Role $role) {
            $wasSystem = (bool) $role->getOriginal('is_system') || RoleName::isSystem((string) $role->getOriginal('name'));

            if ($wasSystem) {
                if ($role->isDirty('name') && $role->name !== $role->getOriginal('name')) {
                    throw new DomainException("Core system role '{$role->getOriginal('name')}' cannot be renamed. Its system identifier is protected.");
                }

                if ($role->isDirty('is_system') && ! $role->is_system) {
                    throw new DomainException("Core system role '{$role->getOriginal('name')}' cannot be converted to a custom role.");
                }
            }
        });

        static::deleting(function (Role $role) {
            if ($role->isSystem()) {
                throw new DomainException("Core system role '{$role->name}' cannot be deleted under any circumstances.");
            }
        });
    }

    public function isSystem(): bool
    {
        return (bool) $this->is_system || RoleName::isSystem((string) $this->name);
    }

    public function getDisplayNameOrNameAttribute(): string
    {
        return $this->display_name ?: $this->name;
    }
}
