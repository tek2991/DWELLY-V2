<?php

namespace App\Filament\Resources\Properties\Pages;

use App\Filament\Resources\Properties\PropertyResource;
use App\Domain\Opportunity\Models\FinancialModel;
use App\Domain\Mou\Enums\MouType;
use App\Domain\Opportunity\Enums\MouStatus;
use App\Domain\Mou\Models\Mou;
use App\Domain\Mou\Services\PropertyUpdateMouService;
use App\Domain\Mou\Services\MouWorkflowService;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Actions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\HtmlString;

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
        $this->loadFormData();
    }

    protected function loadFormData(): void
    {
        $latestPricing = $this->record->financialTerms()->latest('effective_from')->first();
        $mou = $this->record->mous()->latest()->first();

        // Bank Details
        $ownerParty = $this->record->mous()->whereNotNull('party_id')->latest()->first()?->party;
        $primaryBankAccount = $ownerParty?->bankAccounts()->where('is_primary', true)->first();
        $bankDetails = [];
        if ($primaryBankAccount) {
            $bankDetails = [
                'beneficiary_name' => $primaryBankAccount->beneficiary_name,
                'account_number' => $primaryBankAccount->account_number,
                'ifsc_code' => $primaryBankAccount->ifsc_code,
                'bank_name' => $primaryBankAccount->bank_name,
                'bank_address' => $primaryBankAccount->bank_address,
            ];
        } elseif ($mou?->bank_details) {
            $bankDetails = $mou->bank_details;
            if (isset($bankDetails['bank_details']) && is_array($bankDetails['bank_details'])) {
                $bankDetails = $bankDetails['bank_details'];
            }
        }

        // Pricing details
        $financialModelName = $latestPricing?->pricing_model
            ?? $mou?->legal_terms['financial_model_name']
            ?? $mou?->legal_terms['pricing_model']
            ?? (isset($mou?->legal_terms['financial_model_id']) ? FinancialModel::find($mou->legal_terms['financial_model_id'])?->name : null)
            ?? $mou?->opportunity?->expectedFinancialModel?->name;

        $feePercentage = $latestPricing?->fee_percentage
            ?? $mou?->legal_terms['fee_percentage']
            ?? null;

        $this->form->fill([
            'pricing_model' => $financialModelName,
            'fee_percentage' => $feePercentage,
            'bank_details' => $bankDetails,
            'start_date' => $latestPricing?->effective_from?->format('Y-m-d') ?? $mou?->start_date?->format('Y-m-d'),
            'is_signatory_different' => $mou?->is_signatory_different ?? false,
            'signatory_name' => $mou?->signatory_details['name'] ?? null,
            'signatory_relation' => $mou?->signatory_details['relation'] ?? null,
            'signatory_phone' => $mou?->signatory_details['phone'] ?? null,
            'signatory_email' => $mou?->signatory_details['email'] ?? null,
            'signatory_aadhar_number' => $mou?->signatory_details['aadhar_number'] ?? null,
            'signatory_pan_number' => $mou?->signatory_details['pan_number'] ?? null,
        ]);
    }

    public function getPendingPricingMouProperty(): ?Mou
    {
        return app(PropertyUpdateMouService::class)->getPendingUpdateMou($this->record, MouType::PRICING_UPDATE);
    }

    public function getPendingBankMouProperty(): ?Mou
    {
        return app(PropertyUpdateMouService::class)->getPendingUpdateMou($this->record, MouType::BANK_DETAILS_UPDATE);
    }

    public function getPendingSignatoryMouProperty(): ?Mou
    {
        return app(PropertyUpdateMouService::class)->getPendingUpdateMou($this->record, MouType::SIGN_AUTHORITY_UPDATE);
    }

    public function getActivePricingMouProperty(): ?Mou
    {
        $latestPricing = $this->record->financialTerms()->latest('effective_from')->first();
        if ($latestPricing?->mou) {
            return $latestPricing->mou;
        }
        return $this->record->mous()
            ->whereIn('type', [MouType::PRICING_UPDATE, MouType::ONBOARDING])
            ->whereIn('status', [MouStatus::VERIFIED, MouStatus::CONVERTED])
            ->latest('verified_at')
            ->first() ?? $this->record->mous()->latest()->first();
    }

    public function getActiveBankMouProperty(): ?Mou
    {
        return $this->record->mous()
            ->whereIn('type', [MouType::BANK_DETAILS_UPDATE, MouType::ONBOARDING])
            ->whereIn('status', [MouStatus::VERIFIED, MouStatus::CONVERTED])
            ->latest('verified_at')
            ->first() ?? $this->record->mous()->latest()->first();
    }

    public function getActiveSignatoryMouProperty(): ?Mou
    {
        return $this->record->mous()
            ->whereIn('type', [MouType::SIGN_AUTHORITY_UPDATE, MouType::ONBOARDING])
            ->whereIn('status', [MouStatus::VERIFIED, MouStatus::CONVERTED])
            ->latest('verified_at')
            ->first() ?? $this->record->mous()->latest()->first();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('Tabs')
                    ->tabs([
                        Tabs\Tab::make('Pricing Policy')
                            ->schema([
                                Section::make('Active Pricing Policy')
                                    ->description(function () {
                                        $mou = $this->activePricingMou;
                                        if ($mou) {
                                            $media = $mou->getFirstMedia('signed_pdf') ?? $mou->getFirstMedia('draft_pdf');
                                            if ($media) {
                                                $sourceHtml = 'Source MOU: <a href="#" wire:click.prevent="mountAction(\'viewHistoryPdf\', { mediaId: ' . $media->id . ', title: \'' . addslashes($mou->number . ' - Active Pricing MOU') . '\' })" class="text-primary-600 hover:text-primary-500 font-bold underline inline-flex items-center gap-1"><svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg> ' . e($mou->number) . '</a>';
                                            } else {
                                                $sourceHtml = 'Source MOU: <strong>' . e($mou->number) . '</strong>';
                                            }
                                        } else {
                                            $sourceHtml = 'Source MOU: <strong>N/A</strong>';
                                        }
                                        return new HtmlString('Current active pricing terms for this property. ' . $sourceHtml);
                                    })
                                    ->headerActions([
                                        Action::make('initiatePricingUpdate')
                                            ->label('Update Pricing Policy')
                                            ->icon('heroicon-o-currency-rupee')
                                            ->color('primary')
                                            ->visible(fn () => $this->pendingPricingMou === null)
                                            ->form([
                                                Select::make('financial_model_id')
                                                    ->label('Financial Model')
                                                    ->options(fn () => FinancialModel::pluck('name', 'id'))
                                                    ->required(),
                                                TextInput::make('fee_percentage')
                                                    ->label('Fee Percentage')
                                                    ->numeric()
                                                    ->suffix('%')
                                                    ->default(12)
                                                    ->required(),
                                                DatePicker::make('start_date')
                                                    ->label('Effective Start Date')
                                                    ->default(now()->format('Y-m-d'))
                                                    ->required(),
                                            ])
                                            ->action(function (array $data) {
                                                app(PropertyUpdateMouService::class)->initiateUpdate($this->record, MouType::PRICING_UPDATE, $data);
                                                $this->loadFormData();
                                                Notification::make()->title('Pricing Update MOU Initiated')->success()->send();
                                            }),
                                    ])
                                    ->disabled()
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextInput::make('pricing_model')
                                                ->label('Financial Model'),
                                            TextInput::make('fee_percentage')
                                                ->label('Fee Percentage')
                                                ->suffix('%'),
                                            TextInput::make('start_date')
                                                ->label('Effective Date'),
                                        ]),
                                    ]),

                                Section::make('Pending Pricing Update Workflow')
                                    ->visible(fn () => $this->pendingPricingMou !== null)
                                    ->headerActions([
                                        Action::make('generatePricingPdf')
                                            ->label(fn () => $this->pendingPricingMou?->hasMedia('draft_pdf') ? 'Regenerate PDF' : 'Generate PDF')
                                            ->icon('heroicon-o-document-arrow-down')
                                            ->color('warning')
                                            ->visible(fn () => in_array($this->pendingPricingMou?->status, [MouStatus::DRAFT, MouStatus::PDF_GENERATED, MouStatus::DOWNLOADED, MouStatus::SIGNED_COPY_UPLOADED]))
                                            ->action(function () {
                                                $mou = $this->pendingPricingMou;
                                                app(MouWorkflowService::class)->generatePdf($mou);
                                                $this->loadFormData();
                                                Notification::make()->title('Pricing Update MOU PDF Generated')->success()->send();
                                            }),

                                        Action::make('uploadPricingSignedCopy')
                                            ->label('Upload Signed MOU')
                                            ->icon('heroicon-o-document-arrow-up')
                                            ->color('info')
                                            ->visible(fn () => in_array($this->pendingPricingMou?->status, [MouStatus::PDF_GENERATED, MouStatus::DOWNLOADED, MouStatus::SIGNED_COPY_UPLOADED]))
                                            ->form([
                                                FileUpload::make('signed_pdf')
                                                    ->label('Signed PDF File')
                                                    ->directory('temp-signed-pdfs')
                                                    ->acceptedFileTypes(['application/pdf'])
                                                    ->required(),
                                            ])
                                            ->action(function (array $data) {
                                                $mou = $this->pendingPricingMou;
                                                app(MouWorkflowService::class)->uploadSignedCopy($mou, $data['signed_pdf']);
                                                $this->loadFormData();
                                                Notification::make()->title('Signed Pricing MOU Uploaded')->success()->send();
                                            }),

                                        Action::make('verifyPricingUpdate')
                                            ->label('Verify & Apply')
                                            ->icon('heroicon-o-check-badge')
                                            ->color('success')
                                            ->visible(fn () => $this->pendingPricingMou?->status === MouStatus::SIGNED_COPY_UPLOADED)
                                            ->requiresConfirmation()
                                            ->action(function () {
                                                $mou = $this->pendingPricingMou;
                                                app(MouWorkflowService::class)->verify($mou);
                                                $this->loadFormData();
                                                Notification::make()->title('Pricing Update Verified & Applied!')->success()->send();
                                            }),
                                    ])
                                    ->schema([
                                        Placeholder::make('pending_pricing_status')
                                            ->label('Pending Update Status')
                                            ->content(function () {
                                                $mou = $this->pendingPricingMou;
                                                if (!$mou) return '';
                                                $statusLabel = $mou->status?->getLabel() ?? 'Pending';
                                                $media = $mou->getFirstMedia('signed_pdf') ?? $mou->getFirstMedia('draft_pdf');
                                                $docHtml = '';
                                                if ($media) {
                                                    $typeLabel = $media->collection_name === 'signed_pdf' ? 'Signed Copy' : 'Draft PDF';
                                                    $docHtml = ' &nbsp;&bull;&nbsp; <a href="#" wire:click.prevent="mountAction(\'viewHistoryPdf\', { mediaId: ' . $media->id . ', title: \'' . addslashes($mou->number . ' - ' . $typeLabel) . '\' })" class="text-primary-600 hover:text-primary-500 font-medium underline inline-flex items-center gap-1"><svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg> View ' . $typeLabel . '</a>';
                                                }
                                                return new HtmlString(
                                                    '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300">' . 
                                                    e($statusLabel) . 
                                                    '</span> &nbsp; MOU #: <strong>' . e($mou->number) . '</strong>' . $docHtml
                                                );
                                            }),
                                        Grid::make(3)->schema([
                                            Placeholder::make('proposed_pricing_model')
                                                ->label('Proposed Financial Model')
                                                ->content(fn () => $this->pendingPricingMou?->legal_terms['financial_model_name'] ?? 'N/A'),
                                            Placeholder::make('proposed_fee_percentage')
                                                ->label('Proposed Fee Percentage')
                                                ->content(fn () => ($this->pendingPricingMou?->legal_terms['fee_percentage'] ?? '12') . '%'),
                                            Placeholder::make('proposed_start_date')
                                                ->label('Proposed Effective Date')
                                                ->content(fn () => $this->pendingPricingMou?->start_date?->format('j F Y') ?? 'N/A'),
                                        ]),
                                        Actions::make([
                                            Action::make('editPricingProposedDetails')
                                                ->label('Edit Proposed Details')
                                                ->icon('heroicon-o-pencil-square')
                                                ->color('gray')
                                                ->visible(fn () => in_array($this->pendingPricingMou?->status, [MouStatus::DRAFT, MouStatus::PDF_GENERATED, MouStatus::DOWNLOADED]))
                                                ->fillForm(fn () => [
                                                    'financial_model_id' => $this->pendingPricingMou?->legal_terms['financial_model_id'] ?? null,
                                                    'fee_percentage' => $this->pendingPricingMou?->legal_terms['fee_percentage'] ?? 12,
                                                    'start_date' => $this->pendingPricingMou?->start_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
                                                ])
                                                ->form([
                                                    Select::make('financial_model_id')
                                                        ->label('Financial Model')
                                                        ->options(fn () => FinancialModel::pluck('name', 'id'))
                                                        ->required(),
                                                    TextInput::make('fee_percentage')
                                                        ->label('Fee Percentage')
                                                        ->numeric()
                                                        ->suffix('%')
                                                        ->required(),
                                                    DatePicker::make('start_date')
                                                        ->label('Effective Start Date')
                                                        ->required(),
                                                ])
                                                ->action(function (array $data) {
                                                    app(PropertyUpdateMouService::class)->updateProposedDetails($this->pendingPricingMou, $data);
                                                    $this->loadFormData();
                                                    Notification::make()->title('Proposed Pricing Terms Updated')->success()->send();
                                                }),

                                            Action::make('cancelPricingUpdate')
                                                ->label('Cancel Update')
                                                ->icon('heroicon-o-x-circle')
                                                ->color('danger')
                                                ->requiresConfirmation()
                                                ->action(function () {
                                                    $this->pendingPricingMou?->update(['status' => MouStatus::CANCELLED]);
                                                    $this->loadFormData();
                                                    Notification::make()->title('Pricing Update Workflow Cancelled')->success()->send();
                                                }),
                                        ])->alignment(Alignment::End),
                                    ]),
                            ]),

                        Tabs\Tab::make('Bank Details')
                            ->schema([
                                Section::make('Active Bank Account Information')
                                    ->description(function () {
                                        $mou = $this->activeBankMou;
                                        if ($mou) {
                                            $media = $mou->getFirstMedia('signed_pdf') ?? $mou->getFirstMedia('draft_pdf');
                                            if ($media) {
                                                $sourceHtml = 'Source MOU: <a href="#" wire:click.prevent="mountAction(\'viewHistoryPdf\', { mediaId: ' . $media->id . ', title: \'' . addslashes($mou->number . ' - Active Bank MOU') . '\' })" class="text-primary-600 hover:text-primary-500 font-bold underline inline-flex items-center gap-1"><svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg> ' . e($mou->number) . '</a>';
                                            } else {
                                                $sourceHtml = 'Source MOU: <strong>' . e($mou->number) . '</strong>';
                                            }
                                        } else {
                                            $sourceHtml = 'Source MOU: <strong>N/A</strong>';
                                        }
                                        return new HtmlString('Current active bank account for remittances. ' . $sourceHtml);
                                    })
                                    ->headerActions([
                                        Action::make('initiateBankUpdate')
                                            ->label('Update Bank Details')
                                            ->icon('heroicon-o-building-library')
                                            ->color('primary')
                                            ->visible(fn () => $this->pendingBankMou === null)
                                            ->form([
                                                TextInput::make('beneficiary_name')->label('Beneficiary Name')->required(),
                                                TextInput::make('bank_name')->label('Bank Name')->required(),
                                                TextInput::make('account_number')->label('Account Number')->required(),
                                                TextInput::make('ifsc_code')->label('IFSC Code')->required(),
                                                Textarea::make('bank_address')->label('Address of the Bank')->required(),
                                            ])
                                            ->action(function (array $data) {
                                                app(PropertyUpdateMouService::class)->initiateUpdate($this->record, MouType::BANK_DETAILS_UPDATE, $data);
                                                $this->loadFormData();
                                                Notification::make()->title('Bank Details Update MOU Initiated')->success()->send();
                                            }),
                                    ])
                                    ->schema([
                                        Group::make()
                                            ->statePath('bank_details')
                                            ->disabled()
                                            ->schema([
                                                TextInput::make('beneficiary_name')->label('Beneficiary Name'),
                                                TextInput::make('account_number')->label('Account Number'),
                                                TextInput::make('ifsc_code')->label('IFSC Code'),
                                                TextInput::make('bank_name')->label('Bank Name'),
                                                TextInput::make('bank_address')->label('Bank Address'),
                                            ])
                                            ->columns(2),
                                    ]),

                                Section::make('Pending Bank Details Update Workflow')
                                    ->visible(fn () => $this->pendingBankMou !== null)
                                    ->headerActions([
                                        Action::make('generateBankPdf')
                                            ->label(fn () => $this->pendingBankMou?->hasMedia('draft_pdf') ? 'Regenerate PDF' : 'Generate PDF')
                                            ->icon('heroicon-o-document-arrow-down')
                                            ->color('warning')
                                            ->visible(fn () => in_array($this->pendingBankMou?->status, [MouStatus::DRAFT, MouStatus::PDF_GENERATED, MouStatus::DOWNLOADED, MouStatus::SIGNED_COPY_UPLOADED]))
                                            ->action(function () {
                                                $mou = $this->pendingBankMou;
                                                app(MouWorkflowService::class)->generatePdf($mou);
                                                $this->loadFormData();
                                                Notification::make()->title('Bank Details MOU PDF Generated')->success()->send();
                                            }),

                                        Action::make('uploadBankSignedCopy')
                                            ->label('Upload Signed MOU')
                                            ->icon('heroicon-o-document-arrow-up')
                                            ->color('info')
                                            ->visible(fn () => in_array($this->pendingBankMou?->status, [MouStatus::PDF_GENERATED, MouStatus::DOWNLOADED, MouStatus::SIGNED_COPY_UPLOADED]))
                                            ->form([
                                                FileUpload::make('signed_pdf')
                                                    ->label('Signed PDF File')
                                                    ->directory('temp-signed-pdfs')
                                                    ->acceptedFileTypes(['application/pdf'])
                                                    ->required(),
                                            ])
                                            ->action(function (array $data) {
                                                $mou = $this->pendingBankMou;
                                                app(MouWorkflowService::class)->uploadSignedCopy($mou, $data['signed_pdf']);
                                                $this->loadFormData();
                                                Notification::make()->title('Signed Bank MOU Uploaded')->success()->send();
                                            }),

                                        Action::make('verifyBankUpdate')
                                            ->label('Verify & Apply')
                                            ->icon('heroicon-o-check-badge')
                                            ->color('success')
                                            ->visible(fn () => $this->pendingBankMou?->status === MouStatus::SIGNED_COPY_UPLOADED)
                                            ->requiresConfirmation()
                                            ->action(function () {
                                                $mou = $this->pendingBankMou;
                                                app(MouWorkflowService::class)->verify($mou);
                                                $this->loadFormData();
                                                Notification::make()->title('Bank Details Update Verified & Applied!')->success()->send();
                                            }),
                                    ])
                                    ->schema([
                                        Placeholder::make('pending_bank_status')
                                            ->label('Pending Update Status')
                                            ->content(function () {
                                                $mou = $this->pendingBankMou;
                                                if (!$mou) return '';
                                                $statusLabel = $mou->status?->getLabel() ?? 'Pending';
                                                $media = $mou->getFirstMedia('signed_pdf') ?? $mou->getFirstMedia('draft_pdf');
                                                $docHtml = '';
                                                if ($media) {
                                                    $typeLabel = $media->collection_name === 'signed_pdf' ? 'Signed Copy' : 'Draft PDF';
                                                    $docHtml = ' &nbsp;&bull;&nbsp; <a href="#" wire:click.prevent="mountAction(\'viewHistoryPdf\', { mediaId: ' . $media->id . ', title: \'' . addslashes($mou->number . ' - ' . $typeLabel) . '\' })" class="text-primary-600 hover:text-primary-500 font-medium underline inline-flex items-center gap-1"><svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg> View ' . $typeLabel . '</a>';
                                                }
                                                return new HtmlString(
                                                    '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300">' . 
                                                    e($statusLabel) . 
                                                    '</span> &nbsp; MOU #: <strong>' . e($mou->number) . '</strong>' . $docHtml
                                                );
                                            }),
                                        Grid::make(2)->schema([
                                            Placeholder::make('proposed_beneficiary_name')
                                                ->label('Proposed Beneficiary')
                                                ->content(fn () => $this->pendingBankMou?->bank_details['beneficiary_name'] ?? 'N/A'),
                                            Placeholder::make('proposed_bank_name')
                                                ->label('Proposed Bank')
                                                ->content(fn () => $this->pendingBankMou?->bank_details['bank_name'] ?? 'N/A'),
                                            Placeholder::make('proposed_account_number')
                                                ->label('Proposed Account No.')
                                                ->content(fn () => $this->pendingBankMou?->bank_details['account_number'] ?? 'N/A'),
                                            Placeholder::make('proposed_ifsc_code')
                                                ->label('Proposed IFSC Code')
                                                ->content(fn () => $this->pendingBankMou?->bank_details['ifsc_code'] ?? 'N/A'),
                                        ]),
                                        Actions::make([
                                            Action::make('editBankProposedDetails')
                                                ->label('Edit Proposed Details')
                                                ->icon('heroicon-o-pencil-square')
                                                ->color('gray')
                                                ->visible(fn () => in_array($this->pendingBankMou?->status, [MouStatus::DRAFT, MouStatus::PDF_GENERATED, MouStatus::DOWNLOADED]))
                                                ->fillForm(fn () => [
                                                    'beneficiary_name' => $this->pendingBankMou?->bank_details['beneficiary_name'] ?? null,
                                                    'bank_name' => $this->pendingBankMou?->bank_details['bank_name'] ?? null,
                                                    'account_number' => $this->pendingBankMou?->bank_details['account_number'] ?? null,
                                                    'ifsc_code' => $this->pendingBankMou?->bank_details['ifsc_code'] ?? null,
                                                    'bank_address' => $this->pendingBankMou?->bank_details['bank_address'] ?? null,
                                                ])
                                                ->form([
                                                    TextInput::make('beneficiary_name')->label('Beneficiary Name')->required(),
                                                    TextInput::make('bank_name')->label('Bank Name')->required(),
                                                    TextInput::make('account_number')->label('Account Number')->required(),
                                                    TextInput::make('ifsc_code')->label('IFSC Code')->required(),
                                                    Textarea::make('bank_address')->label('Address of the Bank')->required(),
                                                ])
                                                ->action(function (array $data) {
                                                    app(PropertyUpdateMouService::class)->updateProposedDetails($this->pendingBankMou, $data);
                                                    $this->loadFormData();
                                                    Notification::make()->title('Proposed Bank Details Updated')->success()->send();
                                                }),

                                            Action::make('cancelBankUpdate')
                                                ->label('Cancel Update')
                                                ->icon('heroicon-o-x-circle')
                                                ->color('danger')
                                                ->requiresConfirmation()
                                                ->action(function () {
                                                    $this->pendingBankMou?->update(['status' => MouStatus::CANCELLED]);
                                                    $this->loadFormData();
                                                    Notification::make()->title('Bank Details Update Workflow Cancelled')->success()->send();
                                                }),
                                        ])->alignment(Alignment::End),
                                    ]),
                            ]),

                        Tabs\Tab::make('Signatory Details')
                            ->schema([
                                Section::make('Active Signatory Details')
                                    ->description(function () {
                                        $mou = $this->activeSignatoryMou;
                                        if ($mou) {
                                            $media = $mou->getFirstMedia('signed_pdf') ?? $mou->getFirstMedia('draft_pdf');
                                            if ($media) {
                                                $sourceHtml = 'Source MOU: <a href="#" wire:click.prevent="mountAction(\'viewHistoryPdf\', { mediaId: ' . $media->id . ', title: \'' . addslashes($mou->number . ' - Active Signatory MOU') . '\' })" class="text-primary-600 hover:text-primary-500 font-bold underline inline-flex items-center gap-1"><svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg> ' . e($mou->number) . '</a>';
                                            } else {
                                                $sourceHtml = 'Source MOU: <strong>' . e($mou->number) . '</strong>';
                                            }
                                        } else {
                                            $sourceHtml = 'Source MOU: <strong>N/A</strong>';
                                        }
                                        return new HtmlString('Current active signatory authority for this property. ' . $sourceHtml);
                                    })
                                    ->headerActions([
                                        Action::make('initiateSignatoryUpdate')
                                            ->label('Update Signatory Details')
                                            ->icon('heroicon-o-user-plus')
                                            ->color('primary')
                                            ->visible(fn () => $this->pendingSignatoryMou === null)
                                            ->form([
                                                Toggle::make('is_signatory_different')
                                                    ->label('Is Signatory Authority different from Property Owner?')
                                                    ->default(true)
                                                    ->live(),
                                                TextInput::make('signatory_details.name')
                                                    ->label('Signatory Full Name')
                                                    ->required(),
                                                TextInput::make('signatory_details.relation')
                                                    ->label('Relation to Owner (e.g. POA, Son)')
                                                    ->required(),
                                                TextInput::make('signatory_details.phone')
                                                    ->label('Phone Number')
                                                    ->tel(),
                                                TextInput::make('signatory_details.email')
                                                    ->label('Email Address')
                                                    ->email(),
                                                TextInput::make('signatory_details.aadhar_number')
                                                    ->label('Aadhaar Number'),
                                                TextInput::make('signatory_details.pan_number')
                                                    ->label('PAN Number'),
                                            ])
                                            ->action(function (array $data) {
                                                app(PropertyUpdateMouService::class)->initiateUpdate($this->record, MouType::SIGN_AUTHORITY_UPDATE, $data);
                                                $this->loadFormData();
                                                Notification::make()->title('Signatory Details Update MOU Initiated')->success()->send();
                                            }),
                                    ])
                                    ->schema([
                                        Toggle::make('is_signatory_different')
                                            ->label('Is Signatory Authority different from Property Owner?')
                                            ->disabled()
                                            ->live(),

                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('signatory_name')->label('Signatory Full Name'),
                                                TextInput::make('signatory_relation')->label('Relation to Owner'),
                                                TextInput::make('signatory_phone')->label('Phone Number'),
                                                TextInput::make('signatory_email')->label('Email Address'),
                                                TextInput::make('signatory_aadhar_number')->label('Aadhaar Number'),
                                                TextInput::make('signatory_pan_number')->label('PAN Number'),
                                            ])
                                            ->disabled()
                                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('is_signatory_different')),

                                        Grid::make(3)
                                            ->schema([
                                                Placeholder::make('owner_name')
                                                    ->label('Owner Name')
                                                    ->content(fn () => $this->record->mous()->whereNotNull('party_id')->latest()->first()?->party?->display_name ?? $this->record->mous()->latest()->first()?->owner_details['name'] ?? 'N/A'),
                                                Placeholder::make('owner_email')
                                                    ->label('Owner Email')
                                                    ->content(fn () => $this->record->mous()->whereNotNull('party_id')->latest()->first()?->party?->email ?? $this->record->mous()->latest()->first()?->owner_details['email'] ?? 'N/A'),
                                                Placeholder::make('owner_phone')
                                                    ->label('Owner Phone')
                                                    ->content(fn () => $this->record->mous()->whereNotNull('party_id')->latest()->first()?->party?->phone ?? $this->record->mous()->latest()->first()?->owner_details['phone'] ?? 'N/A'),
                                            ])
                                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => !$get('is_signatory_different')),
                                    ]),

                                Section::make('Pending Signatory Update Workflow')
                                    ->visible(fn () => $this->pendingSignatoryMou !== null)
                                    ->headerActions([
                                        Action::make('generateSignatoryPdf')
                                            ->label(fn () => $this->pendingSignatoryMou?->hasMedia('draft_pdf') ? 'Regenerate PDF' : 'Generate PDF')
                                            ->icon('heroicon-o-document-arrow-down')
                                            ->color('warning')
                                            ->visible(fn () => in_array($this->pendingSignatoryMou?->status, [MouStatus::DRAFT, MouStatus::PDF_GENERATED, MouStatus::DOWNLOADED, MouStatus::SIGNED_COPY_UPLOADED]))
                                            ->action(function () {
                                                $mou = $this->pendingSignatoryMou;
                                                app(MouWorkflowService::class)->generatePdf($mou);
                                                $this->loadFormData();
                                                Notification::make()->title('Signatory Details MOU PDF Generated')->success()->send();
                                            }),

                                        Action::make('uploadSignatorySignedCopy')
                                            ->label('Upload Signed MOU')
                                            ->icon('heroicon-o-document-arrow-up')
                                            ->color('info')
                                            ->visible(fn () => in_array($this->pendingSignatoryMou?->status, [MouStatus::PDF_GENERATED, MouStatus::DOWNLOADED, MouStatus::SIGNED_COPY_UPLOADED]))
                                            ->form([
                                                FileUpload::make('signed_pdf')
                                                    ->label('Signed PDF File')
                                                    ->directory('temp-signed-pdfs')
                                                    ->acceptedFileTypes(['application/pdf'])
                                                    ->required(),
                                            ])
                                            ->action(function (array $data) {
                                                $mou = $this->pendingSignatoryMou;
                                                app(MouWorkflowService::class)->uploadSignedCopy($mou, $data['signed_pdf']);
                                                $this->loadFormData();
                                                Notification::make()->title('Signed Signatory MOU Uploaded')->success()->send();
                                            }),

                                        Action::make('verifySignatoryUpdate')
                                            ->label('Verify & Apply')
                                            ->icon('heroicon-o-check-badge')
                                            ->color('success')
                                            ->visible(fn () => $this->pendingSignatoryMou?->status === MouStatus::SIGNED_COPY_UPLOADED)
                                            ->requiresConfirmation()
                                            ->action(function () {
                                                $mou = $this->pendingSignatoryMou;
                                                app(MouWorkflowService::class)->verify($mou);
                                                $this->loadFormData();
                                                Notification::make()->title('Signatory Details Update Verified & Applied!')->success()->send();
                                            }),
                                    ])
                                    ->schema([
                                        Placeholder::make('pending_signatory_status')
                                            ->label('Pending Update Status')
                                            ->content(function () {
                                                $mou = $this->pendingSignatoryMou;
                                                if (!$mou) return '';
                                                $statusLabel = $mou->status?->getLabel() ?? 'Pending';
                                                $media = $mou->getFirstMedia('signed_pdf') ?? $mou->getFirstMedia('draft_pdf');
                                                $docHtml = '';
                                                if ($media) {
                                                    $typeLabel = $media->collection_name === 'signed_pdf' ? 'Signed Copy' : 'Draft PDF';
                                                    $docHtml = ' &nbsp;&bull;&nbsp; <a href="#" wire:click.prevent="mountAction(\'viewHistoryPdf\', { mediaId: ' . $media->id . ', title: \'' . addslashes($mou->number . ' - ' . $typeLabel) . '\' })" class="text-primary-600 hover:text-primary-500 font-medium underline inline-flex items-center gap-1"><svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg> View ' . $typeLabel . '</a>';
                                                }
                                                return new HtmlString(
                                                    '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300">' . 
                                                    e($statusLabel) . 
                                                    '</span> &nbsp; MOU #: <strong>' . e($mou->number) . '</strong>' . $docHtml
                                                );
                                            }),
                                        Grid::make(2)->schema([
                                            Placeholder::make('proposed_signatory_name')
                                                ->label('Proposed Signatory Name')
                                                ->content(fn () => $this->pendingSignatoryMou?->signatory_details['name'] ?? 'Owner Self'),
                                            Placeholder::make('proposed_signatory_relation')
                                                ->label('Relation to Owner')
                                                ->content(fn () => $this->pendingSignatoryMou?->signatory_details['relation'] ?? 'Self'),
                                        ]),
                                        Actions::make([
                                            Action::make('editSignatoryProposedDetails')
                                                ->label('Edit Proposed Details')
                                                ->icon('heroicon-o-pencil-square')
                                                ->color('gray')
                                                ->visible(fn () => in_array($this->pendingSignatoryMou?->status, [MouStatus::DRAFT, MouStatus::PDF_GENERATED, MouStatus::DOWNLOADED]))
                                                ->fillForm(fn () => [
                                                    'is_signatory_different' => $this->pendingSignatoryMou?->is_signatory_different ?? true,
                                                    'signatory_details' => [
                                                        'name' => $this->pendingSignatoryMou?->signatory_details['name'] ?? null,
                                                        'relation' => $this->pendingSignatoryMou?->signatory_details['relation'] ?? null,
                                                        'phone' => $this->pendingSignatoryMou?->signatory_details['phone'] ?? null,
                                                        'email' => $this->pendingSignatoryMou?->signatory_details['email'] ?? null,
                                                        'aadhar_number' => $this->pendingSignatoryMou?->signatory_details['aadhar_number'] ?? null,
                                                        'pan_number' => $this->pendingSignatoryMou?->signatory_details['pan_number'] ?? null,
                                                    ],
                                                ])
                                                ->form([
                                                    Toggle::make('is_signatory_different')
                                                        ->label('Is Signatory Authority different from Property Owner?')
                                                        ->live(),
                                                    TextInput::make('signatory_details.name')
                                                        ->label('Signatory Full Name')
                                                        ->required(),
                                                    TextInput::make('signatory_details.relation')
                                                        ->label('Relation to Owner (e.g. POA, Son)')
                                                        ->required(),
                                                    TextInput::make('signatory_details.phone')
                                                        ->label('Phone Number')
                                                        ->tel(),
                                                    TextInput::make('signatory_details.email')
                                                        ->label('Email Address')
                                                        ->email(),
                                                    TextInput::make('signatory_details.aadhar_number')
                                                        ->label('Aadhaar Number'),
                                                    TextInput::make('signatory_details.pan_number')
                                                        ->label('PAN Number'),
                                                ])
                                                ->action(function (array $data) {
                                                    app(PropertyUpdateMouService::class)->updateProposedDetails($this->pendingSignatoryMou, $data);
                                                    $this->loadFormData();
                                                    Notification::make()->title('Proposed Signatory Details Updated')->success()->send();
                                                }),

                                            Action::make('cancelSignatoryUpdate')
                                                ->label('Cancel Update')
                                                ->icon('heroicon-o-x-circle')
                                                ->color('danger')
                                                ->requiresConfirmation()
                                                ->action(function () {
                                                    $this->pendingSignatoryMou?->update(['status' => MouStatus::CANCELLED]);
                                                    $this->loadFormData();
                                                    Notification::make()->title('Signatory Details Update Workflow Cancelled')->success()->send();
                                                }),
                                        ])->alignment(Alignment::End),
                                    ]),
                            ]),

                        Tabs\Tab::make('Additional Documents')
                            ->schema([
                                \Filament\Schemas\Components\Livewire::make(
                                    \App\Filament\Resources\Properties\RelationManagers\AdditionalDocumentsRelationManager::class,
                                    ['ownerRecord' => $this->record]
                                )->key('additional-documents-relation-manager'),
                            ]),

                        Tabs\Tab::make('MOU Documents')
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
            Action::make('viewHistoryPdf')
                ->extraAttributes(['style' => 'display: none !important;'])
                ->modalHeading(fn (?array $arguments = null) => $arguments['title'] ?? 'View Document')
                ->modalWidth('7xl')
                ->modalSubmitActionLabel('Download Document')
                ->modalCancelActionLabel('Close')
                ->modalContent(function (?array $arguments = null) {
                    $mediaId = $arguments['mediaId'] ?? null;
                    if (!$mediaId) return null;
                    
                    $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::find($mediaId);
                    if (!$media) return null;
                    
                    return view('components.pdf-viewer-raw', [
                        'path' => $media->getPath()
                    ]);
                })
                ->action(function (?array $arguments = null) {
                    $mediaId = $arguments['mediaId'] ?? null;
                    if (!$mediaId) return;
                    
                    $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::find($mediaId);
                    if (!$media) return;
                    
                    return response()->download($media->getPath(), $media->file_name);
                }),
        ];
    }

    public function save(): void
    {
        // Handled via individual update workflow actions
    }
}
