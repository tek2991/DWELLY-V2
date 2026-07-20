<?php

namespace App\Filament\Resources\Properties\Pages;

use App\Filament\Resources\Properties\PropertyResource;
use App\Domain\Opportunity\Models\FinancialModel;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class PropertyFinancials extends Page implements HasForms
{
    use InteractsWithRecord;
    use InteractsWithForms;

    protected static string $resource = PropertyResource::class;

    protected string $view = 'filament.resources.properties.pages.property-financials';

    protected static ?string $title = 'Financial Terms & MOU';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-currency-rupee';

    public ?array $data = [];

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
        
        // Temporarily comment out authorization for easy testing, or handle it via Policy
        // abort_unless(auth()->user()->can('view_financials_property'), 403, 'Unauthorized access to financials.');

        $latestPricing = $this->record->financialTerms()->latest('effective_from')->first();
        $mou = $this->record->mou;

        $bankDetails = $mou?->bank_details ?? [];
        if (!empty($bankDetails) && isset(array_values($bankDetails)[0]) && is_array(array_values($bankDetails)[0])) {
            $bankDetails = array_values($bankDetails)[0];
        }

        $this->form->fill([
            'pricing_model' => $latestPricing?->pricing_model,
            'fee_percentage' => $latestPricing?->fee_percentage,
            'bank_details' => $bankDetails,
            'start_date' => $mou?->start_date,
            'is_signatory_different' => $mou?->is_signatory_different ?? false,
            'signatory_name' => $mou?->signatory_name,
            'signatory_relation' => $mou?->signatory_relation,
            'signatory_phone' => $mou?->signatory_phone,
            'signatory_email' => $mou?->signatory_email,
            'signatory_aadhar_number' => $mou?->signatory_aadhar_number,
            'signatory_pan_number' => $mou?->signatory_pan_number,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                \Filament\Schemas\Components\Tabs::make('Tabs')
                    ->tabs([
                        \Filament\Schemas\Components\Tabs\Tab::make('Pricing Policy')
                            ->schema([
                                Section::make('New Terms')
                                    ->description('Enter the new terms. Saving this will generate a new MOU.')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('pricing_model')
                                                ->options(fn() => FinancialModel::pluck('name', 'name'))
                                                ->required()
                                                ->searchable(),
                                            TextInput::make('fee_percentage')
                                                ->numeric()
                                                ->suffix('%')
                                                ->required(),
                                            \Filament\Forms\Components\DatePicker::make('start_date')
                                                ->label('MOU Start Date')
                                                ->required(),
                                        ]),
                                    ]),
                            ]),
                        \Filament\Schemas\Components\Tabs\Tab::make('Bank Details')
                            ->schema([
                                Section::make('Bank Account Information')
                                    ->description('Enter the bank account details for remittances.')
                                    ->schema([
                                        \Filament\Schemas\Components\Group::make()
                                            ->statePath('bank_details')
                                            ->schema([
                                                TextInput::make('account_name')->required(),
                                                TextInput::make('account_number')->required(),
                                                TextInput::make('ifsc_code')->required(),
                                                TextInput::make('bank_name')->required(),
                                            ])
                                            ->columns(2),
                                    ]),
                            ]),
                        \Filament\Schemas\Components\Tabs\Tab::make('Signatory Details')
                            ->schema([
                                \Filament\Schemas\Components\Section::make('Signatory Details')
                                    ->schema([
                                        \Filament\Forms\Components\Toggle::make('is_signatory_different')
                                            ->label('Is Signatory Authority different from Property Owner?')
                                            ->default(false)
                                            ->live(),

                                        \Filament\Schemas\Components\Grid::make(2)
                                            ->schema([
                                                \Filament\Forms\Components\TextInput::make('signatory_name')
                                                    ->label('Signatory Full Name')
                                                    ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('is_signatory_different')),
                                                \Filament\Forms\Components\TextInput::make('signatory_relation')
                                                    ->label('Relation to Owner (e.g. POA, Son)')
                                                    ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('is_signatory_different')),
                                                \Filament\Forms\Components\TextInput::make('signatory_phone')
                                                    ->label('Phone Number')
                                                    ->tel(),
                                                \Filament\Forms\Components\TextInput::make('signatory_email')
                                                    ->label('Email Address')
                                                    ->email(),
                                                \Filament\Forms\Components\TextInput::make('signatory_aadhar_number')
                                                    ->label('Aadhaar Number'),
                                                \Filament\Forms\Components\TextInput::make('signatory_pan_number')
                                                    ->label('PAN Number'),
                                            ])
                                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('is_signatory_different')),
                                    ]),
                            ]),
                        \Filament\Schemas\Components\Tabs\Tab::make('Additional Documents')
                            ->schema([
                                \Filament\Schemas\Components\Livewire::make(
                                    \App\Filament\Resources\Properties\RelationManagers\AdditionalDocumentsRelationManager::class,
                                    ['ownerRecord' => $this->record]
                                )->key('additional-documents-relation-manager'),
                            ]),
                        \Filament\Schemas\Components\Tabs\Tab::make('MOU Documents')
                            ->schema([
                                \Filament\Schemas\Components\Livewire::make(
                                    \App\Filament\Resources\Properties\RelationManagers\MappedDocumentsRelationManager::class,
                                    ['ownerRecord' => $this->record, 'pageClass' => static::class]
                                )->key('mapped-documents-relation-manager'),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateMou')
                ->label('Generate New MOU')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function () {
                    $data = $this->form->getState();
                    
                    $mou = $this->record->mou;
                    if (!$mou) {
                        $mou = \App\Domain\Mou\Models\Mou::create([
                            'status' => 'draft',
                            'prepared_by' => auth()->id(),
                            'bank_details' => $data['bank_details'] ?? [],
                            'start_date' => $data['start_date'] ?? null,
                            'is_signatory_different' => $data['is_signatory_different'] ?? false,
                            'signatory_name' => $data['signatory_name'] ?? null,
                            'signatory_relation' => $data['signatory_relation'] ?? null,
                            'signatory_phone' => $data['signatory_phone'] ?? null,
                            'signatory_email' => $data['signatory_email'] ?? null,
                            'signatory_aadhar_number' => $data['signatory_aadhar_number'] ?? null,
                            'signatory_pan_number' => $data['signatory_pan_number'] ?? null,
                        ]);
                        $this->record->update(['mou_id' => $mou->id]);
                    } else {
                        $mou->update([
                            'bank_details' => $data['bank_details'] ?? [],
                            'start_date' => $data['start_date'] ?? $mou->start_date,
                            'is_signatory_different' => $data['is_signatory_different'] ?? false,
                            'signatory_name' => $data['signatory_name'] ?? null,
                            'signatory_relation' => $data['signatory_relation'] ?? null,
                            'signatory_phone' => $data['signatory_phone'] ?? null,
                            'signatory_email' => $data['signatory_email'] ?? null,
                            'signatory_aadhar_number' => $data['signatory_aadhar_number'] ?? null,
                            'signatory_pan_number' => $data['signatory_pan_number'] ?? null,
                        ]);
                    }

                    \App\Domain\Property\Models\PropertyFinancialTerm::create([
                        'property_id' => $this->record->id,
                        'mou_id' => $mou->id,
                        'pricing_model' => $data['pricing_model'],
                        'fee_percentage' => $data['fee_percentage'],
                        'effective_from' => now()->toDateString(),
                        'created_by' => auth()->id(),
                    ]);
                    
                    $pdfService = app(\App\Domain\Mou\Services\MouPdfService::class);
                    $pdfService->saveMouPdf($mou);
                    
                    Notification::make()
                        ->title('MOU Generated successfully!')
                        ->success()
                        ->send();
                })
        ];
    }

    public function save(): void
    {
        // Dummy save method to handle form submission if triggered by enter key
    }
}
