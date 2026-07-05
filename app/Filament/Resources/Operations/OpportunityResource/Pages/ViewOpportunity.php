<?php

namespace App\Filament\Resources\Operations\OpportunityResource\Pages;

use App\Filament\Resources\Operations\OpportunityResource;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Opportunity\Enums\OpportunityStatus;
use App\Domain\Opportunity\Services\OpportunityWorkflowService;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\App;

class ViewOpportunity extends ViewRecord
{
    protected static string $resource = OpportunityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            
            Actions\Action::make('markContacted')
                ->label('Mark Contacted')
                ->icon('heroicon-o-phone')
                ->color('primary')
                ->visible(fn (Opportunity $record) => $record->status === OpportunityStatus::NEW)
                ->form([
                    Forms\Components\Textarea::make('notes')->label('Notes'),
                ])
                ->action(fn (Opportunity $record, array $data) => app(OpportunityWorkflowService::class)->markContacted($record, $data['notes'] ?? null)),

            Actions\Action::make('scheduleSiteVisit')
                ->label('Schedule Site Visit')
                ->icon('heroicon-o-calendar')
                ->color('warning')
                ->visible(fn (Opportunity $record) => in_array($record->status, [OpportunityStatus::NEW, OpportunityStatus::CONTACTED]))
                ->form([
                    Forms\Components\DatePicker::make('date')->required(),
                    Forms\Components\Textarea::make('notes')->label('Notes'),
                ])
                ->action(fn (Opportunity $record, array $data) => app(OpportunityWorkflowService::class)->scheduleSiteVisit($record, $data['date'], $data['notes'] ?? null)),
                
            Actions\Action::make('completeSiteVisit')
                ->label('Complete Site Visit')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (Opportunity $record) => $record->status === OpportunityStatus::SITE_VISIT_SCHEDULED)
                ->form([
                    Forms\Components\Textarea::make('notes')->label('Notes'),
                ])
                ->action(fn (Opportunity $record, array $data) => app(OpportunityWorkflowService::class)->completeSiteVisit($record, $data['notes'] ?? null)),
                
            Actions\Action::make('startNegotiation')
                ->label('Start Negotiation')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('purple')
                ->visible(fn (Opportunity $record) => in_array($record->status, [OpportunityStatus::SITE_VISIT_COMPLETED, OpportunityStatus::CONTACTED]))
                ->form([
                    Forms\Components\Textarea::make('notes')->label('Notes'),
                ])
                ->action(fn (Opportunity $record, array $data) => app(OpportunityWorkflowService::class)->startNegotiation($record, $data['notes'] ?? null)),
                
            Actions\Action::make('generateMOU')
                ->label('Prepare MOU')
                ->icon('heroicon-o-document-text')
                ->color('warning')
                ->visible(fn (Opportunity $record) => !in_array($record->status, [OpportunityStatus::MOU_PENDING, OpportunityStatus::MOU_SIGNED, OpportunityStatus::CLOSED_LOST, OpportunityStatus::CANCELLED, OpportunityStatus::CONVERTED]))
                ->steps([
                    \Filament\Schemas\Components\Wizard\Step::make('Legal Entity')
                        ->description('Who are we signing with?')
                        ->schema([
                            Forms\Components\Radio::make('party_type')
                                ->label('Entity Type')
                                ->options([
                                    'individual' => 'Individual',
                                    'company' => 'Company',
                                ])
                                ->required()
                                ->live(),
                            Forms\Components\TextInput::make('legal_name')
                                ->label(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('party_type') === 'company' ? 'Company Name' : 'Full Name')
                                ->required(),
                            Forms\Components\TextInput::make('pan_number')
                                ->label('PAN Number')
                                ->required(),
                            Forms\Components\TextInput::make('gst_number')
                                ->label('GST Number')
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('party_type') === 'company'),
                            Forms\Components\TextInput::make('aadhar_number')
                                ->label('Aadhar Number')
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('party_type') === 'individual'),
                        ]),
                    \Filament\Schemas\Components\Wizard\Step::make('Bank Details')
                        ->description('For payouts')
                        ->schema([
                            Forms\Components\TextInput::make('bank_name')->required(),
                            Forms\Components\TextInput::make('account_number')->required(),
                            Forms\Components\TextInput::make('ifsc_code')->required(),
                            Forms\Components\TextInput::make('account_holder_name')->required(),
                        ]),
                    \Filament\Schemas\Components\Wizard\Step::make('Legal Terms')
                        ->description('Core commercial terms')
                        ->schema([
                            Forms\Components\TextInput::make('rent_amount')
                                ->label('Agreed Rent (INR)')
                                ->numeric()
                                ->required()
                                ->default(fn (Opportunity $record) => $record->expected_rent),
                            Forms\Components\TextInput::make('security_deposit')
                                ->label('Security Deposit (INR)')
                                ->numeric()
                                ->required(),
                            Forms\Components\TextInput::make('lock_in_months')
                                ->label('Lock-in Period (Months)')
                                ->numeric()
                                ->required(),
                            Forms\Components\TextInput::make('notice_period_months')
                                ->label('Notice Period (Months)')
                                ->numeric()
                                ->required(),
                            Forms\Components\Textarea::make('notes')->label('Additional Notes'),
                        ]),
                ])
                ->action(fn (Opportunity $record, array $data) => app(OpportunityWorkflowService::class)->generateMOU($record, $data)),
                
            Actions\Action::make('uploadSignedMOU')
                ->label('Upload Signed MOU')
                ->icon('heroicon-o-document-check')
                ->color('success')
                ->visible(fn (Opportunity $record) => $record->status === OpportunityStatus::MOU_PENDING)
                ->form([
                    Forms\Components\Textarea::make('notes')->label('Notes'),
                ])
                ->action(fn (Opportunity $record, array $data) => app(OpportunityWorkflowService::class)->uploadSignedMOU($record, $data['notes'] ?? null)),
                
            Actions\Action::make('closeLost')
                ->label('Close Lost')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (Opportunity $record) => !in_array($record->status, [OpportunityStatus::CLOSED_LOST, OpportunityStatus::CANCELLED, OpportunityStatus::CONVERTED]))
                ->form([
                    Forms\Components\Textarea::make('notes')->label('Notes'),
                ])
                ->action(fn (Opportunity $record, array $data) => app(OpportunityWorkflowService::class)->closeLost($record, $data['notes'] ?? null)),
        ];
    }
}
