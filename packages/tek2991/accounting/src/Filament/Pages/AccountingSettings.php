<?php

namespace Tek2991\Accounting\Filament\Pages;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Tek2991\Accounting\Models\Organization;

class AccountingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Accounting Settings';
    
    protected ?string $heading = 'Accounting Settings';

    protected string $view = 'accounting::filament.pages.accounting-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $organization = Organization::current();
        
        if ($organization) {
            $this->form->fill($organization->toArray());
        } else {
            $this->form->fill([
                'default_currency' => 'INR',
                'tax_regime' => 'india_gst',
            ]);
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Profile')
                    ->description('Primary details for your organization.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('legal_name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('trade_name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('pan')
                            ->label('PAN')
                            ->maxLength(10),
                    ]),
                    
                Section::make('Accounting Preferences')
                    ->description('Base accounting settings for the organization.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('default_currency')
                            ->label('Default Currency')
                            ->options($this->getCurrencyOptions())
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('tax_regime')
                            ->options([
                                'india_gst' => 'India GST',
                                'none' => 'None',
                            ])
                            ->required(),
                    ]),

                Section::make('Contact Information')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('website')
                            ->maxLength(255),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $organization = Organization::current();

        if ($organization) {
            $organization->update($data);
        } else {
            Organization::create($data);
        }

        Notification::make()
            ->success()
            ->title('Settings Saved')
            ->body('Organization settings have been updated successfully.')
            ->send();
    }

    protected function getCurrencyOptions(): array
    {
        try {
            $names = \Symfony\Component\Intl\Currencies::getNames();
            $formatted = [];
            foreach ($names as $code => $name) {
                $formatted[$code] = "{$name} ({$code})";
            }
            return $formatted;
        } catch (\Throwable) {
            return [
                'INR' => 'Indian Rupee (INR)',
                'USD' => 'US Dollar (USD)',
                'EUR' => 'Euro (EUR)',
                'GBP' => 'British Pound (GBP)',
                'AUD' => 'Australian Dollar (AUD)',
                'CAD' => 'Canadian Dollar (CAD)',
                'SGD' => 'Singapore Dollar (SGD)',
            ];
        }
    }
}
