<?php

namespace App\Filament\Resources\Operations\OpportunityResource\Pages;

use App\Filament\Resources\Operations\OpportunityResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Domain\Opportunity\Enums\OpportunityStatus;
use App\Domain\Opportunity\Actions\GenerateOpportunityNumberAction;
use App\Domain\Opportunity\Services\OpportunityActivityLogger;
use App\Domain\Opportunity\Enums\OpportunityActivityType;

class CreateOpportunity extends CreateRecord
{
    protected static string $resource = OpportunityResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['number'] = app(GenerateOpportunityNumberAction::class)->execute();
        $data['status'] = OpportunityStatus::NEW->value;
        $data['assigned_user_id'] = $data['assigned_user_id'] ?? auth()->id();
        return $data;
    }

    protected function afterCreate(): void
    {
        app(OpportunityActivityLogger::class)->log(
            $this->record,
            OpportunityActivityType::CREATED,
            'Opportunity Created',
            ['initial_status' => OpportunityStatus::NEW->value]
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
