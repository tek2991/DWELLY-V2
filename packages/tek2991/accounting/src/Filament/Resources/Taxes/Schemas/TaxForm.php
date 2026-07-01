<?php

namespace Tek2991\Accounting\Filament\Resources\Taxes\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Set;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Enums\TaxComponentType;
use Tek2991\Accounting\Services\CompanyContext;

class TaxForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Tax Details')
                    ->columns(2)
                    ->components([
                        Forms\Components\TextInput::make('name')
                            ->label('Tax Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. GST 18%'),
                            
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                            
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),
                    
                Section::make('Tax Components')
                    ->description('Define the individual breakdown (e.g. CGST 9%, SGST 9%)')
                    ->components([
                        Actions::make([
                            \Filament\Actions\Action::make('generate_gst')
                                ->label('Generate GST Components')
                                ->icon('heroicon-m-sparkles')
                                ->visible(fn () => app(CompanyContext::class)->isIndiaGst())
                                ->form([
                                    Forms\Components\TextInput::make('total_rate')
                                        ->label('Total GST Rate (%)')
                                        ->numeric()
                                        ->required()
                                        ->helperText('e.g. 18 for GST 18%'),
                                    Forms\Components\Select::make('sales_account_id')
                                        ->label('Sales Tax Account (Liability)')
                                        ->options(fn () => \Tek2991\Accounting\Models\Account::where('type', AccountType::Liability)->pluck('name', 'id'))
                                        ->searchable()
                                        ->preload()
                                        ->required(),
                                    Forms\Components\Select::make('purchase_account_id')
                                        ->label('Purchase Tax Account (Asset)')
                                        ->options(fn () => \Tek2991\Accounting\Models\Account::where('type', AccountType::Asset)->pluck('name', 'id'))
                                        ->searchable()
                                        ->preload()
                                        ->required(),
                                ])
                                ->action(function (array $data, Set $set) {
                                    $halfRate = $data['total_rate'] / 2;
                                    
                                    $components = [
                                        (string) str()->uuid() => [
                                            'name' => "CGST {$halfRate}%",
                                            'rate' => $halfRate,
                                            'type' => TaxComponentType::Intrastate->value,
                                            'sales_account_id' => $data['sales_account_id'],
                                            'purchase_account_id' => $data['purchase_account_id'],
                                        ],
                                        (string) str()->uuid() => [
                                            'name' => "SGST {$halfRate}%",
                                            'rate' => $halfRate,
                                            'type' => TaxComponentType::Intrastate->value,
                                            'sales_account_id' => $data['sales_account_id'],
                                            'purchase_account_id' => $data['purchase_account_id'],
                                        ],
                                        (string) str()->uuid() => [
                                            'name' => "IGST {$data['total_rate']}%",
                                            'rate' => $data['total_rate'],
                                            'type' => TaxComponentType::Interstate->value,
                                            'sales_account_id' => $data['sales_account_id'],
                                            'purchase_account_id' => $data['purchase_account_id'],
                                        ]
                                    ];
                                    
                                    $set('components', $components);
                                }),
                        ]),
                        Forms\Components\Repeater::make('components')
                            ->relationship()
                            ->columns(4)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Component Name')
                                    ->required()
                                    ->placeholder('e.g. CGST'),
                                    
                                Forms\Components\TextInput::make('rate')
                                    ->label('Rate (%)')
                                    ->numeric()
                                    ->required()
                                    ->step('0.0001')
                                    ->suffix('%'),
                                    
                                Forms\Components\Select::make('type')
                                    ->label('Component Type')
                                    ->options(TaxComponentType::class)
                                    ->default(TaxComponentType::Generic->value)
                                    ->visible(fn () => app(CompanyContext::class)->isIndiaGst())
                                    ->required(),
                                    
                                Forms\Components\Select::make('sales_account_id')
                                    ->label('Sales Tax Account (Liability)')
                                    ->relationship('salesAccount', 'name', fn ($query) => $query->where('type', AccountType::Liability))
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                    
                                Forms\Components\Select::make('purchase_account_id')
                                    ->label('Purchase Tax Account (Asset)')
                                    ->relationship('purchaseAccount', 'name', fn ($query) => $query->where('type', AccountType::Asset))
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                            ])
                            ->defaultItems(1)
                            ->addActionLabel('Add Component')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
                    ]),
            ]);
    }
}
