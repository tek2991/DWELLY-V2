<?php

namespace App\Filament\Resources\Settings\OpportunitySourceResource\Pages;

use App\Filament\Resources\Settings\OpportunitySourceResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageOpportunitySources extends ManageRecords
{
    protected static string $resource = OpportunitySourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
