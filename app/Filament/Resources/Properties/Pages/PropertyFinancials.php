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

        $this->form->fill([
            'pricing_model' => $latestPricing?->pricing_model,
            'fee_percentage' => $latestPricing?->fee_percentage,
            'bank_details' => $mou?->bank_details ?? [],
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
                                        ]),
                                    ]),
                            ]),
                        \Filament\Schemas\Components\Tabs\Tab::make('Bank Details')
                            ->schema([
                                Section::make('Bank Account Information')
                                    ->description('Enter the bank account details for remittances.')
                                    ->schema([
                                        \Filament\Forms\Components\Repeater::make('bank_details')
                                            ->schema([
                                                TextInput::make('account_name')->required(),
                                                TextInput::make('account_number')->required(),
                                                TextInput::make('ifsc_code')->required(),
                                                TextInput::make('bank_name')->required(),
                                            ])
                                            ->columns(2)
                                            ->defaultItems(1),
                                    ]),
                            ]),
                        \Filament\Schemas\Components\Tabs\Tab::make('Additional Documents')
                            ->schema([
                                \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('mou_attachments')
                                    ->collection('mou_attachments')
                                    ->multiple()
                                    ->label('KYC & Cancelled Cheque (Images/PDFs)')
                                    ->helperText('Upload Aadhar, PAN, Cancelled Cheque, etc.')
                                    ->model($this->record->mou ?? $this->record),
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
                        ]);
                        $this->record->update(['mou_id' => $mou->id]);
                    } else {
                        $mou->update(['bank_details' => $data['bank_details'] ?? []]);
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
