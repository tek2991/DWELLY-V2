<?php

namespace App\Filament\Resources\Administration;

use App\Domain\Auth\Services\PermissionCatalog;
use App\Filament\Clusters\AdministrationCluster;
use App\Filament\Forms\Components\RolePermissionsMatrix;
use App\Filament\Resources\Administration\RoleResource\Pages;
use App\Models\Role;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $cluster = AdministrationCluster::class;

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', Role::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', Role::class) ?? false;
    }

    public static function canEdit(?Model $record = null): bool
    {
        if (! $record) {
            return auth()->user()?->can('create', Role::class) ?? false;
        }

        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        if ($record instanceof Role && $record->isSystem()) {
            return false;
        }

        return auth()->user()?->can('delete', $record) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                Section::make('Role Identity & Responsibility')
                    ->description('General configuration and machine code identifier for this role.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('System Code Identifier')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->disabled(fn (?Role $record) => $record?->isSystem() ?? false)
                            ->helperText(fn (?Role $record) => $record?->isSystem()
                                ? '🔒 Core system role. Machine code identifier is immutable to guarantee system stability.'
                                : 'Unique internal machine identifier for this role.'
                            ),

                        Forms\Components\TextInput::make('display_name')
                            ->label('Display Title')
                            ->placeholder(fn (?Role $record) => $record?->name ?? 'e.g. Regional Director')
                            ->maxLength(255)
                            ->helperText('Human-readable title displayed across the UI. Can be freely customized anytime.'),

                        Forms\Components\Textarea::make('description')
                            ->label('Role Purpose & Responsibilities')
                            ->maxLength(65535)
                            ->rows(2)
                            ->columnSpanFull()
                            ->helperText('Brief overview of the responsibilities and scope granted to holders of this role.'),
                    ]),

                Section::make('Role Permissions & Access Control')
                    ->description('Configure granular business capabilities, financial authorities, and module visibility for this role.')
                    ->columnSpanFull()
                    ->schema([
                        RolePermissionsMatrix::make('permissions')
                            ->hiddenLabel()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Role Title')
                    ->default(fn (Role $record) => $record->name)
                    ->searchable(['display_name', 'name'])
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label('System Code')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('is_system')
                    ->label('Protection')
                    ->badge()
                    ->formatStateUsing(fn ($state, Role $record) => $record->isSystem() ? '🔒 Core System' : 'Custom')
                    ->color(fn ($state, Role $record) => $record->isSystem() ? 'danger' : 'info'),

                Tables\Columns\TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Permissions')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                Action::make('resetDefaults')
                    ->label('Reset Defaults')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Role $record) => $record->isSystem())
                    ->requiresConfirmation()
                    ->modalHeading(fn (Role $record) => "Reset '{$record->display_name_or_name}' Permissions?")
                    ->modalDescription(fn (Role $record) => "This will overwrite all currently assigned permissions with the standard factory defaults for '{$record->display_name_or_name}'. Any custom additions or revocations will be replaced. Proceed?")
                    ->modalSubmitActionLabel('Yes, Reset to Defaults')
                    ->action(function (Role $record) {
                        $defaults = PermissionCatalog::getDefaultsForRole($record->name);
                        $record->syncPermissions($defaults);

                        Notification::make()
                            ->title('Role Permissions Reset')
                            ->body("Permissions for '{$record->display_name_or_name}' have been restored to system defaults.")
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()
                    ->visible(fn (Role $record) => ! $record->isSystem()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function (Collection $records) {
                            $records->each(function (Role $record) {
                                if (! $record->isSystem()) {
                                    $record->delete();
                                }
                            });
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
