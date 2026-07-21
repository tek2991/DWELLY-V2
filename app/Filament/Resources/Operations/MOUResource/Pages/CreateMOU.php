<?php

namespace App\Filament\Resources\Operations\MOUResource\Pages;

use App\Filament\Resources\Operations\MOUResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Domain\Opportunity\Models\Opportunity;

class CreateMOU extends CreateRecord
{
    protected static string $resource = MOUResource::class;
    
    public function mount(): void
    {
        parent::mount();
        
        $opportunityId = request()->query('opportunity_id');
        if ($opportunityId) {
            $opportunity = Opportunity::find($opportunityId);
            if ($opportunity) {
                $this->form->fill([
                    'opportunity_id' => $opportunity->id,
                    'legal_terms' => [
                        'rent_amount' => $opportunity->expected_rent,
                        'address' => $opportunity->address,
                        'financial_model_id' => $opportunity->expected_financial_model_id,
                    ],
                ]);
            }
        }
    }
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['number'] = app(\App\Domain\Mou\Actions\GenerateMouNumberAction::class)->execute();
        $data['status'] = \App\Domain\Opportunity\Enums\MouStatus::DRAFT;
        $data['prepared_by'] = auth()->id();
        
        if (!empty($data['legal_terms']['financial_model_id'])) {
            $model = \App\Domain\Opportunity\Models\FinancialModel::find($data['legal_terms']['financial_model_id']);
            if ($model) {
                $data['legal_terms']['financial_model_name'] = $model->name;
                $data['legal_terms']['financial_model_description'] = $model->description;
                $data['legal_terms']['financial_model_fee_collection'] = $model->fee_collection;
            }
        }
        
        if (!empty($data['legal_terms']['electricity_provider_id'])) {
            $provider = \App\Domain\Property\Models\UtilityProvider::find($data['legal_terms']['electricity_provider_id']);
            if ($provider) {
                $data['legal_terms']['electricity_provider_name'] = $provider->name;
            }
        }

        unset($data['legal_terms']['pricing_model'], $data['legal_terms']['fee_percentage']);
        
        if (empty($data['is_signatory_different'])) {
            $party = !empty($data['party_id']) ? \App\Domain\Party\Models\Party::find($data['party_id']) : null;
            $opportunity = !empty($data['opportunity_id']) ? Opportunity::find($data['opportunity_id']) : null;
            $data['signatory_details'] = app(\App\Domain\Mou\Services\MouService::class)->getSignatoryDetailsForOwner($party, $opportunity);
        }
        
        return $data;
    }

    public function canCreateAnother(): bool
    {
        return false;
    }

    protected function afterCreate(): void
    {
        $mou = $this->record;
        if ($mou->opportunity) {
            $mou->opportunity->update([
                'status' => \App\Domain\Opportunity\Enums\OpportunityStatus::MOU_CREATED
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
