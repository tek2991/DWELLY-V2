<?php

namespace App\Filament\Resources\Billing;

use App\Filament\Resources\Billing\Pages\ListBills;
use App\Filament\Resources\Billing\Schemas\BillForm;
use App\Filament\Resources\Billing\Tables\BillsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Tek2991\Accounting\Models\Bill;
use Tek2991\Accounting\Services\BranchContext;

class BillsResource extends Resource
{
    protected static ?string $model = Bill::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowDown;

    protected static \UnitEnum|string|null $navigationGroup = 'Billing & Finance';

    protected static ?string $navigationLabel = 'Bills & Payables';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Bill';

    protected static ?string $pluralModelLabel = 'Bills & Payables';

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        if ($user->roles->isEmpty()) {
            return true;
        }

        return $user->can('viewAny', Bill::class);
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        if ($user->roles->isEmpty()) {
            return true;
        }

        return $user->can('create', Bill::class);
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        if ($user->roles->isEmpty()) {
            return true;
        }

        return $user->can('update', $record);
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        if ($user->roles->isEmpty()) {
            return true;
        }

        return $user->can('delete', $record);
    }

    public static function form(Schema $schema): Schema
    {
        return BillForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BillsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['contact', 'payments']);

        app(BranchContext::class)->applyQueryScope($query);

        return $query;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBills::route('/'),
        ];
    }
}
