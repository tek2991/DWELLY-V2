<?php

namespace App\Filament\Resources\Administration;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Property\Models\Property;
use App\Filament\Clusters\AdministrationCluster;
use App\Filament\Resources\Administration\AuditLogResource\Pages;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

class AuditLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $cluster = AdministrationCluster::class;

    protected static ?string $navigationLabel = 'Audit Logs';

    protected static ?string $modelLabel = 'Audit Log';

    protected static ?string $pluralModelLabel = 'Audit Logs';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(?Model $record = null): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasRole(RoleName::BUSINESS_OWNER)
            || $user->can('admin.audit_logs.viewAny')
            || $user->hasAnyRole([RoleName::CITY_MANAGER, RoleName::ACCOUNTANT])
            || $user->roles->isEmpty();
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->latest('id');
        $user = auth()->user();

        if (! $user || $user->hasRole(RoleName::BUSINESS_OWNER) || $user->roles->isEmpty()) {
            return $query;
        }

        if ($user->hasRole(RoleName::CITY_MANAGER)) {
            $branchIds = $user->branches->pluck('id')->toArray();

            $userIdsInBranches = User::whereHas('branches', function ($b) use ($branchIds) {
                $b->whereIn('branches.id', $branchIds);
            })->pluck('id')->push($user->id)->toArray();

            $propertyIdsInBranches = Property::whereIn('branch_id', $branchIds)->pluck('id')->toArray();

            return $query->where(function ($q) use ($userIdsInBranches, $propertyIdsInBranches) {
                $q->whereIn('causer_id', $userIdsInBranches)
                    ->orWhere(function ($sq) use ($propertyIdsInBranches) {
                        $sq->where('subject_type', Property::class)
                            ->whereIn('subject_id', $propertyIdsInBranches);
                    });
            });
        }

        if ($user->hasRole(RoleName::ACCOUNTANT)) {
            $financialModels = [
                'App\Domain\Finance\Models\OwnerPayout',
                'App\Domain\Finance\Models\OwnerPayoutItem',
                'App\Domain\Billing\Models\RentDemand',
                'App\Domain\Billing\Models\RentCollection',
                'App\Domain\Billing\Models\MaintenanceQuotation',
                'App\Domain\Agreement\Models\TenantDeboarding',
                'Dwelly\Accounting\Models\Transaction',
                'Dwelly\Accounting\Models\Entry',
                'Dwelly\Accounting\Models\Account',
            ];

            return $query->where(function ($q) use ($financialModels) {
                $q->whereIn('subject_type', $financialModels)
                    ->orWhereIn('log_name', ['finance', 'accounting', 'payout', 'settlement', 'billing'])
                    ->orWhere('description', 'LIKE', '%payout%')
                    ->orWhere('description', 'LIKE', '%finance%')
                    ->orWhere('description', 'LIKE', '%settlement%')
                    ->orWhere('description', 'LIKE', '%invoice%')
                    ->orWhere('description', 'LIKE', '%payment%');
            });
        }

        return $query->whereRaw('1 = 0');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('User / Actor')
                    ->searchable()
                    ->default('System'),
                Tables\Columns\TextColumn::make('event')
                    ->badge()
                    ->label('Event')
                    ->color(fn (?string $state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'info',
                    }),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Subject Type')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('log_name')
                    ->badge()
                    ->label('Domain')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('subject_id')
                    ->label('Subject ID')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Forensic Activity Record')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Timestamp')
                                    ->dateTime(),
                                TextEntry::make('event')
                                    ->badge()
                                    ->label('Action Event'),
                                TextEntry::make('causer.name')
                                    ->label('Actor / Causer')
                                    ->placeholder('System Process'),
                                TextEntry::make('causer.email')
                                    ->label('Actor Email')
                                    ->placeholder('system@dwelly.internal'),
                                TextEntry::make('log_name')
                                    ->badge()
                                    ->label('Log Domain'),
                                TextEntry::make('subject_type')
                                    ->label('Subject Type')
                                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—'),
                                TextEntry::make('subject_id')
                                    ->label('Subject ID')
                                    ->placeholder('—'),
                            ]),
                        TextEntry::make('description')
                            ->label('Audit Description')
                            ->columnSpanFull(),
                        TextEntry::make('properties')
                            ->label('Snapshot / Changes Payload')
                            ->formatStateUsing(fn ($state) => is_string($state) ? $state : json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
            'view' => Pages\ViewAuditLog::route('/{record}'),
        ];
    }
}
