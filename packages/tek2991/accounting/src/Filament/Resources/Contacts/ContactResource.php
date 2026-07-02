<?php

namespace Tek2991\Accounting\Filament\Resources\Contacts;

use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Tek2991\Accounting\Models\Contact;
use Tek2991\Accounting\Filament\Resources\Contacts\Pages;
use Tek2991\Accounting\Filament\Resources\Contacts\Schemas\ContactForm;
use Tek2991\Accounting\Filament\Resources\Contacts\Tables\ContactsTable;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-users';
    
    protected static \UnitEnum|string|null $navigationGroup = 'Catalog';
    
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ContactForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContactsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            // Relations like Invoices or Bills could go here
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContacts::route('/'),
            'create' => Pages\CreateContact::route('/create'),
            'edit' => Pages\EditContact::route('/{record}/edit'),
        ];
    }
    
    public static function canCreate(): bool
    {
        return config('accounting.contacts.allow_create', true);
    }
    
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return config('accounting.contacts.allow_delete', true);
    }
    
    public static function canDeleteAny(): bool
    {
        return config('accounting.contacts.allow_delete', true);
    }
}
