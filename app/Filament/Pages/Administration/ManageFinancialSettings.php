<?php

namespace App\Filament\Pages\Administration;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Shared\Services\SettingService;
use App\Filament\Clusters\AdministrationCluster;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ManageFinancialSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = AdministrationCluster::class;

    protected static ?string $navigationLabel = 'Financials';

    protected static ?string $title = 'Financial Settings';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.administration.manage-financial-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasRole(RoleName::BUSINESS_OWNER)
            || $user->can('admin.masters.viewAny')
            || $user->hasAnyRole([RoleName::CITY_MANAGER, RoleName::ACCOUNTANT])
            || $user->roles->isEmpty();
    }

    public function mount(): void
    {
        $this->form->fill([
            'default_margin_percentage' => SettingService::get('financials.default_margin_percentage', 10.00),
            'default_gst_percentage' => SettingService::get('financials.default_gst_percentage', 18.00),
            'default_quotation_validity_days' => SettingService::get('financials.default_quotation_validity_days', 14),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $canManage = auth()->user()?->hasRole(RoleName::BUSINESS_OWNER)
            || auth()->user()?->can('admin.masters.manage')
            || (auth()->user()?->roles->isEmpty() ?? true);

        return $schema
            ->schema([
                Section::make('💵 Maintenance Quotation & Pricing Defaults')
                    ->description('Set system-wide financial defaults applied automatically when preparing maintenance quotations for property owners and tenants.')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('default_margin_percentage')
                                    ->label('Dwelly Coordination / Margin Markup (%)')
                                    ->helperText('Default coordination markup added on top of contractor base estimates.')
                                    ->numeric()
                                    ->suffix('%')
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->disabled(! $canManage)
                                    ->required(),

                                TextInput::make('default_gst_percentage')
                                    ->label('GST / Tax Rate (%)')
                                    ->helperText('Standard GST percentage calculated on taxable client estimates.')
                                    ->numeric()
                                    ->suffix('%')
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->disabled(! $canManage)
                                    ->required(),

                                TextInput::make('default_quotation_validity_days')
                                    ->label('Quotation Validity (Days)')
                                    ->helperText('Default validity duration (in days) from quotation creation.')
                                    ->numeric()
                                    ->suffix('days')
                                    ->minValue(1)
                                    ->maxValue(365)
                                    ->disabled(! $canManage)
                                    ->required(),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        $canManage = auth()->user()?->hasRole(RoleName::BUSINESS_OWNER)
            || auth()->user()?->can('admin.masters.manage')
            || (auth()->user()?->roles->isEmpty() ?? true);

        if (! $canManage) {
            return [];
        }

        return [
            Action::make('save')
                ->label('Save Financial Settings')
                ->icon('heroicon-o-check-circle')
                ->color('primary')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $canManage = auth()->user()?->hasRole(RoleName::BUSINESS_OWNER)
            || auth()->user()?->can('admin.masters.manage')
            || (auth()->user()?->roles->isEmpty() ?? true);

        if (! $canManage) {
            abort(403, 'Unauthorized to modify financial system settings.');
        }

        $state = $this->form->getState();

        SettingService::set(
            'financials.default_margin_percentage',
            (float) ($state['default_margin_percentage'] ?? 10.00),
            'decimal',
            'Default Dwelly margin markup percentage on maintenance quotations'
        );

        SettingService::set(
            'financials.default_gst_percentage',
            (float) ($state['default_gst_percentage'] ?? 18.00),
            'decimal',
            'Default GST percentage on maintenance quotations'
        );

        SettingService::set(
            'financials.default_quotation_validity_days',
            (int) ($state['default_quotation_validity_days'] ?? 14),
            'integer',
            'Default validity duration in days for maintenance quotations'
        );

        Notification::make()
            ->title('Financial Settings Saved')
            ->body('System financial defaults have been updated successfully.')
            ->success()
            ->send();
    }
}
