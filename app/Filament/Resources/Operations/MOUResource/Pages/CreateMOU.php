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
        return $data;
    }
}
