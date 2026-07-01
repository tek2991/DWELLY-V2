<?php

namespace Tek2991\Accounting\Filament\Resources\Items\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Enums\ItemType;

class ItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->columns(2)
                    ->components([
                        Forms\Components\Select::make('type')
                            ->label('Item Type')
                            ->options(ItemType::class)
                            ->default(ItemType::Goods)
                            ->required()
                            ->reactive(),
                            
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),
                            
                        Forms\Components\TextInput::make('sku')
                            ->label('SKU')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                            
                        Forms\Components\TextInput::make('hsn_sac')
                            ->label('HSN / SAC Code')
                            ->maxLength(255),
                            
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                    ]),
                    
                Section::make('Sales Information')
                    ->columns(2)
                    ->components([
                        Forms\Components\Toggle::make('sellable')
                            ->label('I sell this item')
                            ->default(true)
                            ->reactive(),
                            
                        Forms\Components\TextInput::make('sale_price')
                            ->label('Selling Price')
                            ->numeric()
                            ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('sellable'))
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('sellable'))
                            ->prefix(config('accounting.default_currency', 'USD')),
                            
                        Forms\Components\Select::make('income_account_id')
                            ->label('Income Account')
                            ->relationship('incomeAccount', 'name', fn ($query) => $query->where('type', AccountType::Revenue))
                            ->searchable()
                            ->preload()
                            ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('sellable'))
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('sellable')),
                    ]),
                    
                Section::make('Purchase Information')
                    ->columns(2)
                    ->components([
                        Forms\Components\Toggle::make('purchasable')
                            ->label('I purchase this item')
                            ->default(true)
                            ->reactive(),
                            
                        Forms\Components\TextInput::make('purchase_price')
                            ->label('Purchase Price')
                            ->numeric()
                            ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('purchasable'))
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('purchasable'))
                            ->prefix(config('accounting.default_currency', 'USD')),
                            
                        Forms\Components\Select::make('expense_account_id')
                            ->label('Expense / COGS Account')
                            ->relationship('expenseAccount', 'name', fn ($query) => $query->where('type', AccountType::Expense))
                            ->searchable()
                            ->preload()
                            ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('purchasable'))
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('purchasable')),
                    ]),
            ]);
    }
}
