<?php

namespace App\Filament\Resources\Billing;

use App\Filament\Resources\Billing\Pages\CreateRentInvoice;
use App\Filament\Resources\Billing\Pages\EditRentInvoice;
use App\Filament\Resources\Billing\Pages\ListRentInvoices;
use App\Filament\Resources\Billing\Schemas\RentInvoiceForm;
use App\Filament\Resources\Billing\Tables\RentInvoicesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Tek2991\Accounting\Models\Invoice;
use App\Domain\Agreement\Models\TenancyAgreement;

class RentInvoicesResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static \UnitEnum|string|null $navigationGroup = 'Billing & Finance';

    protected static ?string $navigationLabel = 'Rent Invoices & Receipts';

    protected static ?string $modelLabel = 'Rent Invoice';

    protected static ?string $pluralModelLabel = 'Rent Invoices & Receipts';

    public static function form(Schema $schema): Schema
    {
        return RentInvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RentInvoicesTable::configure($table);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->where(function ($query) {
                $query->where('reference_type', TenancyAgreement::class)
                    ->orWhere('notes', 'like', '%Rent%');
            });
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRentInvoices::route('/'),
            'create' => CreateRentInvoice::route('/create'),
            'edit' => EditRentInvoice::route('/{record}/edit'),
        ];
    }
}
