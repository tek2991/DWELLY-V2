<?php

namespace App\Filament\Resources\Administration\RoleResource\Pages;

use App\Domain\Auth\Services\PermissionCatalog;
use App\Filament\Resources\Administration\RoleResource;
use App\Models\Role;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('resetDefaults')
                ->label('Reset to System Defaults')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => $this->getRecord() instanceof Role && $this->getRecord()->isSystem())
                ->requiresConfirmation()
                ->modalHeading(fn (): string => "Reset '{$this->getRecord()->display_name_or_name}' to System Defaults?")
                ->modalDescription('This will overwrite all currently assigned permissions with standard system factory defaults. Any custom permissions granted or revoked will be replaced. Are you sure you want to proceed?')
                ->modalSubmitActionLabel('Yes, Reset Permissions')
                ->action(function () {
                    /** @var Role $role */
                    $role = $this->getRecord();
                    $defaults = PermissionCatalog::getDefaultsForRole($role->name);
                    $role->syncPermissions($defaults);

                    $this->fillForm();

                    Notification::make()
                        ->title('Permissions Reset Successfully')
                        ->body("Permissions for '{$role->display_name_or_name}' have been restored to factory system defaults.")
                        ->success()
                        ->send();
                }),

            Actions\DeleteAction::make()
                ->visible(fn (): bool => $this->getRecord() instanceof Role && ! $this->getRecord()->isSystem()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
