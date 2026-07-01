<?php

namespace Tek2991\Accounting\Filament\Resources\Accounts\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Enums\ReportingClass;
use Tek2991\Accounting\Enums\SystemRole;
use Tek2991\Accounting\Models\Account;

class AccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account Details')
                    ->columns(2)
                    ->components([
                        Forms\Components\Select::make('type')
                            ->label('Category')
                            ->options(AccountType::class)
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (\Filament\Schemas\Components\Utilities\Set $set) {
                                $set('reporting_class', null);
                            }),

                        Forms\Components\Select::make('reporting_class')
                            ->options(function (\Filament\Schemas\Components\Utilities\Get $get) {
                                $type = $get('type');
                                if (! $type) {
                                    return ReportingClass::class; // Or all if type not selected
                                }
                                $typeEnum = $type instanceof AccountType
                                    ? $type
                                    : AccountType::from($type);

                                return collect(ReportingClass::cases())
                                    ->filter(fn (ReportingClass $rc) => $rc->getAccountType() === $typeEnum)
                                    ->mapWithKeys(fn (ReportingClass $rc) => [$rc->value => $rc->getLabel()]);
                            })
                            ->required(),

                        Forms\Components\TextInput::make('code')
                            ->nullable()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('parent_id')
                            ->label('Parent Account')
                            ->relationship('parent', 'name')
                            ->options(function (\Filament\Schemas\Components\Utilities\Get $get, ?Account $record) {
                                $type = $get('type');
                                $query = Account::query();
                                
                                if ($type) {
                                    $query->where('type', $type);
                                }

                                if ($record) {
                                    $query->where('id', '!=', $record->id);
                                }

                                return $query->pluck('name', 'id');
                            })
                            ->searchable()
                            ->nullable(),

                        Forms\Components\Select::make('system_role')
                            ->options(SystemRole::class)
                            ->nullable(),
                            
                        Forms\Components\TextInput::make('currency_code')
                            ->label('Currency')
                            ->default(fn () => \Tek2991\Accounting\Facades\Accounting::getCurrency())
                            ->required()
                            ->maxLength(3),

                        Forms\Components\Textarea::make('description')
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),

                Section::make('Status')
                    ->columns(2)
                    ->components([
                        Forms\Components\Toggle::make('is_control_account')
                            ->label('Is Control Account')
                            ->default(false)
                            ->helperText('Control accounts do not allow manual journal entries.'),
                            
                        Forms\Components\Toggle::make('archived')
                            ->label('Archived')
                            ->default(false),

                        Forms\Components\Toggle::make('default')
                            ->label('Default Account')
                            ->default(false)
                            ->helperText('Mark as the default account for this category'),
                    ]),
            ]);
    }
}
