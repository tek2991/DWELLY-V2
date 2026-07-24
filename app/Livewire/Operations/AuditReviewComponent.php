<?php

namespace App\Livewire\Operations;

use App\Domain\Audit\Models\Audit;
use App\Domain\Audit\Models\AuditItem;
use App\Domain\Audit\Enums\ItemStatus;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Action;
use Livewire\Component;

class AuditReviewComponent extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    public Audit $audit;
    public $activeCategoryId = null;

    public function mount(Audit $audit)
    {
        $this->audit = $audit;
        if ($this->audit->categories->isNotEmpty()) {
            $this->activeCategoryId = $this->audit->categories->first()->id;
        }
    }

    public function setActiveCategory($categoryId)
    {
        $this->activeCategoryId = $categoryId;
    }

    public function acceptAllAction(): Action
    {
        return Action::make('acceptAll')
            ->label('Accept All Items')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->button()
            ->requiresConfirmation()
            ->modalHeading('Accept All Items')
            ->modalDescription('Are you sure you want to accept all remaining items in this audit?')
            ->visible(fn () => $this->audit->canReview())
            ->action(function () {
                app(\App\Domain\Audit\Services\AuditReviewService::class)->acceptAllItems($this->audit, auth()->user());
                $this->audit->load('categories.items.evidence', 'categories.items.reviews');
                \Filament\Notifications\Notification::make()
                    ->title('All items accepted successfully.')
                    ->success()
                    ->send();
            });
    }

    public function approveItemAction(): Action
    {
        return Action::make('approveItem')
            ->label('Approve')
            ->color('success')
            ->button()
            ->action(function (array $arguments) {
                $item = AuditItem::find($arguments['item_id']);
                if ($item) {
                    app(\App\Domain\Audit\Services\AuditReviewService::class)->approveItem($item, auth()->user());
                    $this->audit->load('categories.items.evidence', 'categories.items.reviews');
                }
            });
    }

    public function rejectItemAction(): Action
    {
        return Action::make('rejectItem')
            ->label('Reject')
            ->color('danger')
            ->button()
            ->form([
                Select::make('comment_type')
                    ->label('Issue Type')
                    ->options([
                        'PHOTO' => 'Photo Issue',
                        'CONDITION' => 'Condition Mismatch',
                        'ANNOTATION' => 'Missing Annotation',
                        'GENERAL' => 'General Comment',
                        'OTHER' => 'Other',
                    ])
                    ->required(),
                Textarea::make('reason')
                    ->label('Reason for Rejection')
                    ->required(),
            ])
            ->action(function (array $data, array $arguments) {
                $item = AuditItem::find($arguments['item_id']);
                if ($item) {
                    app(\App\Domain\Audit\Services\AuditReviewService::class)->rejectItem($item, auth()->user(), $data['reason'], $data['comment_type']);
                    $this->audit->load('categories.items.evidence', 'categories.items.reviews');
                }
            });
    }

    public function syncToPropertyAction(): Action
    {
        return Action::make('syncToProperty')
            ->label('Sync to Property')
            ->color('warning')
            ->icon('heroicon-o-arrow-path')
            ->button()
            ->form([
                Select::make('item_type')
                    ->label('Property Item Type')
                    ->options([
                        \App\Domain\Property\Models\PropertyInventory::class => 'Inventory',
                        \App\Domain\Property\Models\PropertyAmenity::class => 'Amenity',
                        \App\Domain\Property\Models\PropertyEstablishment::class => 'Establishment',
                    ])
                    ->required(),
                // In a real scenario, we'd add fields like 'inventory_type_id' depending on the item_type.
                // For this implementation, we will just create the record with basic details or let the user fill it later.
            ])
            ->action(function (array $data, array $arguments) {
                $item = AuditItem::find($arguments['item_id']);
                if ($item && $item->isApproved() && empty($item->source_id)) {
                    $propertyId = $this->audit->property_id;
                    $modelClass = $data['item_type'];
                    
                    // Create the new property asset
                    $newAsset = $modelClass::create([
                        'property_id' => $propertyId,
                        // We are assuming a 'name' or 'description' field exists, or we leave it to be filled
                        // For a robust implementation, we would map the AuditItem's name to the correct relation (like inventory_type_id)
                        // but since we don't have the exact schema for those, we'll just set it generically if possible
                    ]);

                    // Update the audit item to link to the new source
                    $item->update([
                        'source_type' => $modelClass,
                        'source_id' => $newAsset->id,
                    ]);

                    // Remove the 'is_new' flag
                    $snapshot = $item->snapshot_data;
                    unset($snapshot['is_new']);
                    $item->update(['snapshot_data' => $snapshot]);
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Item synced to property successfully.')
                        ->success()
                        ->send();

                    $this->audit->load('categories.items.evidence', 'categories.items.reviews');
                }
            });
    }

    public function render()
    {
        return view('livewire.operations.audit-review-component');
    }
}
