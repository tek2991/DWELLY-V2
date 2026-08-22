<?php

namespace App\Filament\Resources\Operations\OpportunityResource\Pages;

use App\Domain\Opportunity\Enums\OpportunityStatus;
use App\Domain\Opportunity\Models\Opportunity;
use App\Filament\Resources\Operations\OpportunityResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class EditOpportunity extends EditRecord
{
    protected static string $resource = OpportunityResource::class;

    public function getSubheading(): ?Htmlable
    {
        /** @var Opportunity $record */
        $record = $this->getRecord();
        $status = $record->status ?? OpportunityStatus::NEW;
        $statusLabel = e($status->getLabel());

        $ownerBadge = $record->owner_name
            ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200">👤 ' . e($record->owner_name) . '</span>'
            : '';

        $rentBadge = $record->expected_rent > 0
            ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200">💰 ₹' . number_format((float) $record->expected_rent) . ' /mo</span>'
            : '';

        return new HtmlString(
            '<div class="flex items-center gap-2 text-sm text-gray-500 mt-1 flex-wrap">' .
            '<span>Status: <strong class="text-gray-900 dark:text-gray-100">' . $statusLabel . '</strong></span>' .
            ($ownerBadge ? '<span class="text-gray-300 dark:text-gray-700">&bull;</span>' . $ownerBadge : '') .
            ($rentBadge ? '<span class="text-gray-300 dark:text-gray-700">&bull;</span>' . $rentBadge : '') .
            '</div>'
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}

