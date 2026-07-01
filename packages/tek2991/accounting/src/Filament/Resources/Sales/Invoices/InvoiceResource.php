<?php

namespace Tek2991\Accounting\Filament\Resources\Sales\Invoices;

use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Filament\Resources\Sales\Invoices\Pages;
use Tek2991\Accounting\Filament\Resources\Sales\Invoices\Schemas\InvoiceForm;
use Tek2991\Accounting\Filament\Resources\Sales\Invoices\Tables\InvoicesTable;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return InvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
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
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'view' => Pages\ViewInvoice::route('/{record}'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        app(\Tek2991\Accounting\Services\BranchContext::class)->applyQueryScope($query);
        return $query;
    }
}
