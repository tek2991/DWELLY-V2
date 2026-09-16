<?php

namespace App\Filament\Resources\Billing;

use App\Filament\Resources\Billing\Pages\ListInvoices;
use App\Filament\Resources\Billing\Schemas\InvoiceForm;
use App\Filament\Resources\Billing\Tables\InvoicesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Services\BranchContext;

class InvoicesResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static \UnitEnum|string|null $navigationGroup = 'Billing & Finance';

    protected static ?string $navigationLabel = 'Invoices & Receivables';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Invoice';

    protected static ?string $pluralModelLabel = 'Invoices & Receivables';

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        if ($user->roles->isEmpty()) {
            return true;
        }

        return $user->can('viewAny', Invoice::class);
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

        return $user->can('create', Invoice::class);
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
        return InvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
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
            'index' => ListInvoices::route('/'),
        ];
    }
}
