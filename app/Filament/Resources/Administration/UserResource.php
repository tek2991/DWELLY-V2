<?php

namespace App\Filament\Resources\Administration;

use App\Domain\Auth\Enums\RoleName;
use App\Filament\Clusters\AdministrationCluster;
use App\Filament\Resources\Administration\UserResource\Pages;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $cluster = AdministrationCluster::class;

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', User::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', User::class) ?? false;
    }

    public static function canEdit(?Model $record = null): bool
    {
        if (! $record) {
            return auth()->user()?->can('create', User::class) ?? false;
        }

        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
    }

    public static function canDeleteAny(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasRole(RoleName::BUSINESS_OWNER) || $user->can('admin.users.delete') || $user->roles->isEmpty();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user || $user->hasRole(RoleName::BUSINESS_OWNER) || $user->roles->isEmpty()) {
            return $query;
        }

        if ($user->hasRole(RoleName::CITY_MANAGER)) {
            $branchIds = $user->branches->pluck('id')->toArray();

            return $query->where(function ($q) use ($branchIds, $user) {
                $q->whereHas('branches', function ($b) use ($branchIds) {
                    $b->whereIn('branches.id', $branchIds);
                })->orWhere('id', $user->id);
            });
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context): bool => $context === 'create')
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_active')
                    ->label('Is Active (Enable/Block)')
                    ->default(true),
                Forms\Components\Select::make('roles')
                    ->multiple()
                    ->relationship('roles', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name ? "{$record->display_name} ({$record->name})" : $record->name)
                    ->preload()
                    ->visible(fn () => auth()->user()?->can('admin.roles.assign') || auth()->user()?->hasRole(RoleName::BUSINESS_OWNER)),
                Forms\Components\Select::make('branches')
                    ->multiple()
                    ->relationship(
                        'branches',
                        'name',
                        modifyQueryUsing: fn ($query) => (auth()->user()?->hasRole(RoleName::BUSINESS_OWNER) || auth()->user()?->roles->isEmpty())
                            ? $query
                            : $query->whereIn('branches.id', auth()->user()?->branches->pluck('id') ?? [])
                    )
                    ->preload(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                Tables\Columns\TextColumn::make('roles.name')
                    ->badge(),
                Tables\Columns\TextColumn::make('branches.name')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->boolean(),
            ])
            ->actions([
                EditAction::make(),
                Action::make('toggle_status')
                    ->label(fn (User $record) => $record->is_active ? 'Block' : 'Enable')
                    ->color(fn (User $record) => $record->is_active ? 'danger' : 'success')
                    ->icon(fn (User $record) => $record->is_active ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle')
                    ->visible(fn (User $record) => auth()->user()?->can('update', $record) ?? false)
                    ->action(function (User $record) {
                        $record->update(['is_active' => ! $record->is_active]);
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
