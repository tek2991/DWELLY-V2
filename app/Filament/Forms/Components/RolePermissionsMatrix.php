<?php

namespace App\Filament\Forms\Components;

use App\Domain\Auth\Services\PermissionCatalog;
use App\Models\Role;
use Filament\Forms\Components\Field;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;

class RolePermissionsMatrix extends Field
{
    protected string $view = 'filament.forms.components.role-permissions-matrix';

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadStateFromRelationshipsUsing(static function (RolePermissionsMatrix $component): void {
            $record = $component->getRecord();

            if ($record instanceof Role) {
                $component->state($record->permissions()->pluck('name')->all());
            } else {
                $component->state([]);
            }
        });

        $this->saveRelationshipsUsing(static function (RolePermissionsMatrix $component, Model $record): void {
            if ($record instanceof Role) {
                $state = $component->getState();
                $record->syncPermissions(is_array($state) ? $state : []);
            }
        });

        $this->dehydrated(false);
    }

    /**
     * @return array<string, array{
     *     category: array{id: string, label: string, icon: string, description: string, sort: int},
     *     permissions: array<string, array{code: string, label: string, description: string, category: string, risk: string}>
     * }>
     */
    public function getGroupedPermissions(): array
    {
        $grouped = PermissionCatalog::getGroupedPermissions();
        $catalogPermissions = PermissionCatalog::getPermissions();

        // Check if database contains any uncataloged permissions
        try {
            $dbPermissions = Permission::pluck('name')->all();
            $uncataloged = array_diff($dbPermissions, array_keys($catalogPermissions));

            if (! empty($uncataloged)) {
                $grouped['other'] = [
                    'category' => [
                        'id' => 'other',
                        'label' => 'Additional & Plugin Permissions',
                        'icon' => 'heroicon-o-puzzle-piece',
                        'description' => 'Dynamic or plugin-specific permissions detected in the database.',
                        'sort' => 999,
                    ],
                    'permissions' => [],
                ];

                foreach ($uncataloged as $code) {
                    $grouped['other']['permissions'][$code] = [
                        'code' => $code,
                        'label' => str($code)->headline()->toString(),
                        'description' => 'Custom permission: '.$code,
                        'category' => 'other',
                        'risk' => 'action',
                    ];
                }
            }
        } catch (\Throwable $e) {
            // In migration or test edge cases, fallback gracefully
        }

        return $grouped;
    }

    /**
     * @return array<string, array{code: string, label: string, description: string, category: string, risk: string}>
     */
    public function getAllPermissions(): array
    {
        $all = PermissionCatalog::getPermissions();

        try {
            $dbPermissions = Permission::pluck('name')->all();
            $uncataloged = array_diff($dbPermissions, array_keys($all));

            foreach ($uncataloged as $code) {
                $all[$code] = [
                    'code' => $code,
                    'label' => str($code)->headline()->toString(),
                    'description' => 'Custom permission: '.$code,
                    'category' => 'other',
                    'risk' => 'action',
                ];
            }
        } catch (\Throwable $e) {
            // Fallback gracefully
        }

        return $all;
    }

    public function isSystemRole(): bool
    {
        $record = $this->getRecord();

        return $record instanceof Role && $record->isSystem();
    }

    public function getRoleRecord(): ?Role
    {
        $record = $this->getRecord();

        return $record instanceof Role ? $record : null;
    }

    /**
     * @return array<string>
     */
    public function getDefaultPermissions(): array
    {
        $record = $this->getRoleRecord();

        if (! $record) {
            return [];
        }

        return PermissionCatalog::getDefaultsForRole($record->name);
    }
}
