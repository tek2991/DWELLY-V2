<?php

namespace App\Filament\Resources\Parties\Pages;

use App\Domain\Party\Enums\BusinessRole;
use App\Domain\Party\Enums\VendorOnboardingStatus;
use App\Domain\Party\Services\VendorOnboardingService;
use App\Filament\Resources\Parties\PartyResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditParty extends EditRecord
{
    protected static string $resource = PartyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verifyVendor')
                ->label('Verify & Approve Vendor')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => $this->record->hasRole(BusinessRole::VENDOR) && $this->record->vendorProfile?->onboarding_status !== VendorOnboardingStatus::VERIFIED)
                ->requiresConfirmation()
                ->modalHeading('Verify & Approve Vendor')
                ->modalDescription(fn () => "Approve {$this->record->display_name} as an active, verified vendor for maintenance assignments.")
                ->form([
                    Textarea::make('verification_notes')
                        ->label('Verification Remarks / Credentials')
                        ->placeholder('e.g. Verified trade license, identity proof, and tax details.')
                        ->default(fn () => $this->record->vendorProfile?->verification_notes),
                ])
                ->action(function (array $data) {
                    $profile = $this->record->vendorProfile;
                    if (!$profile) return;

                    app(VendorOnboardingService::class)->verifyVendor(
                        profile: $profile,
                        verifier: auth()->user(),
                        notes: $data['verification_notes'] ?? null
                    );

                    Notification::make()
                        ->title('Vendor Verified & Approved')
                        ->body("{$this->record->display_name} is now approved and available for maintenance work orders.")
                        ->success()
                        ->send();

                    $this->fillForm();
                }),

            Action::make('suspendVendor')
                ->label('Suspend Vendor')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->visible(fn () => $this->record->hasRole(BusinessRole::VENDOR) && $this->record->vendorProfile?->onboarding_status === VendorOnboardingStatus::VERIFIED)
                ->requiresConfirmation()
                ->modalHeading('Suspend Vendor')
                ->modalDescription(fn () => "Are you sure you want to suspend {$this->record->display_name}? They will not appear in maintenance assignment dropdowns.")
                ->form([
                    Textarea::make('suspension_reason')
                        ->label('Suspension Reason')
                        ->placeholder('e.g. Unresponsive to work orders / poor workmanship quality')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $profile = $this->record->vendorProfile;
                    if (!$profile) return;

                    app(VendorOnboardingService::class)->suspendVendor(
                        profile: $profile,
                        reason: $data['suspension_reason'] ?? 'Suspended by admin'
                    );

                    Notification::make()
                        ->title('Vendor Suspended')
                        ->body("{$this->record->display_name} has been suspended.")
                        ->warning()
                        ->send();

                    $this->fillForm();
                }),

            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['is_bank_editing_unlocked'] = false;

        if ($this->record->party_type === 'individual' && $this->record->individual) {
            $data['individual_data'] = $this->record->individual->toArray();
        } elseif ($this->record->party_type === 'organization' && $this->record->organization) {
            $data['organization_data'] = $this->record->organization->toArray();
        }
        
        $roles = [];
        if ($this->record->ownerProfile()->exists()) {
            $roles[] = 'owner';
        }
        if ($this->record->tenantProfile()->exists()) {
            $roles[] = 'tenant';
        }
        if ($this->record->vendorProfile()->exists()) {
            $roles[] = 'vendor';
            $vProfile = $this->record->vendorProfile;
            $data['vendor_data'] = [
                'vendor_trade_id' => $vProfile->vendor_trade_id,
                'onboarding_status' => $vProfile->onboarding_status?->value ?? $vProfile->onboarding_status,
                'is_preferred' => $vProfile->is_preferred,
                'verification_notes' => $vProfile->verification_notes,
            ];
        }
        $data['roles'] = $roles;

        $bank = $this->record->bankAccounts()->where('is_primary', true)->first();
        if ($bank) {
            $data['bank_details'] = [
                'beneficiary_name' => $bank->beneficiary_name,
                'bank_name' => $bank->bank_name,
                'bank_address' => $bank->bank_address,
                'account_number' => $bank->account_number,
                'ifsc_code' => $bank->ifsc_code,
            ];
        }

        $primary = $this->record->addresses()->where('is_primary', true)->first();
        $billing = $this->record->addresses()->where('type', 'billing')->first();
        $shipping = $this->record->addresses()->where('type', 'shipping')->first();
        if ($primary || $billing || $shipping) {
            $data['address_details'] = [
                'primary_address' => $primary?->address_line_1,
                'billing_address' => $billing?->address_line_1,
                'shipping_address' => $shipping?->address_line_1,
            ];
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(\App\Domain\Party\Services\PartyService::class)->updateParty($record, $data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
