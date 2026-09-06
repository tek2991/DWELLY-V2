<?php

namespace App\Filament\Resources\Settings\Branches;

use App\Filament\Clusters\AdministrationCluster;
use App\Filament\Resources\Settings\Branches\Pages\CreateBranch;
use App\Filament\Resources\Settings\Branches\Pages\EditBranch;
use App\Filament\Resources\Settings\Branches\Pages\ListBranches;
use App\Models\Branch;
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

class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $cluster = AdministrationCluster::class;

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', Branch::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', Branch::class) ?? false;
    }

    public static function canEdit(?Model $record = null): bool
    {
        if (! $record) {
            return auth()->user()?->can('create', Branch::class) ?? false;
        }

        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user || $user->hasRole('Business Owner') || $user->roles->isEmpty()) {
            return $query;
        }

        if ($user->hasRole('City Manager')) {
            $branchIds = $user->branches->pluck('id')->toArray();

            return $query->whereIn('id', $branchIds);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('organization_id')
                    ->relationship('organization', 'name')
                    ->required(),
                Forms\Components\Select::make('gst_registration_id')
                    ->relationship('gstRegistration', 'gstin'),
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('code')->required()->maxLength(50),
                Forms\Components\Textarea::make('address')->maxLength(65535)->columnSpanFull(),
                Forms\Components\TextInput::make('city')->maxLength(255),
                Forms\Components\TextInput::make('district')->maxLength(255),
                Forms\Components\Select::make('state_id')
                    ->relationship('state', 'name')
                    ->required(),
                Forms\Components\TextInput::make('postal_code')->maxLength(20),
                Forms\Components\TextInput::make('phone')->tel()->maxLength(255),
                Forms\Components\TextInput::make('email')->email()->maxLength(255),
                Forms\Components\Toggle::make('is_active')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('organization.name')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('code')->searchable(),
                Tables\Columns\TextColumn::make('city')->searchable(),
                Tables\Columns\TextColumn::make('state.name')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBranches::route('/'),
            'create' => CreateBranch::route('/create'),
            'edit' => EditBranch::route('/{record}/edit'),
        ];
    }
}
