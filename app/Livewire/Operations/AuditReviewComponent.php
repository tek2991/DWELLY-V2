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
    public $referenceItems = [];
    public ?string $referenceAuditNumber = null;
    public $activeCategoryId = null;

    public function mount(Audit $audit)
    {
        $this->audit = $audit->load('media');
        if ($this->audit->categories->isNotEmpty()) {
            $this->activeCategoryId = $this->audit->categories->first()->id;
        }
        $this->loadReferenceItems();
    }

    public function loadReferenceItems(): void
    {
        $this->referenceItems = [];
        $this->referenceAuditNumber = null;

        if ($this->audit->reference_audit_id) {
            $referenceAudit = Audit::with(['items.evidence.media'])->find($this->audit->reference_audit_id);
            if ($referenceAudit) {
                $this->referenceAuditNumber = $referenceAudit->audit_number ?? $referenceAudit->id;

                foreach ($referenceAudit->items as $refItem) {
                    $key = ($refItem->source_type && $refItem->source_id)
                        ? ($refItem->source_type . '_' . $refItem->source_id)
                        : ('name_' . mb_strtolower(trim($refItem->name)));

                    $evidenceList = [];
                    foreach ($refItem->evidence as $ev) {
                        $media = $ev->getFirstMedia('images');
                        $url = '';
                        if ($media) {
                            $url = $media->getUrl();
                            $appUrl = rtrim(config('app.url'), '/');
                            if (str_starts_with($url, $appUrl)) {
                                $url = substr($url, strlen($appUrl));
                                if (!str_starts_with($url, '/')) {
                                    $url = '/' . $url;
                                }
                            }
                        } else {
                            $url = $ev->getFirstMediaUrl();
                        }

                        if ($url) {
                            $hasAnnotations = !empty($ev->annotation_json) 
                                && isset($ev->annotation_json['canvas']['objects']) 
                                && count($ev->annotation_json['canvas']['objects']) > 0;

                            $evidenceList[] = [
                                'id' => $ev->id,
                                'url' => $url,
                                'annotation_json' => $ev->annotation_json,
                                'has_annotations' => $hasAnnotations,
                            ];
                        }
                    }

                    $this->referenceItems[$key] = [
                        'id' => $refItem->id,
                        'name' => $refItem->name,
                        'condition' => $refItem->condition ? $refItem->condition->getLabel() : 'Not Set',
                        'condition_color' => $refItem->condition?->getColor() ?? 'gray',
                        'remarks' => $refItem->remarks,
                        'evidence' => $evidenceList,
                    ];
                }
            }
        }
    }

    public function setActiveCategory($categoryId)
    {
        $this->activeCategoryId = $categoryId;
    }

    public function refreshAuditRelations(): void
    {
        $this->audit->unsetRelation('categories');
        $this->audit->unsetRelation('items');
        $this->audit->load('categories.items.evidence', 'categories.items.reviews', 'categories.items.category', 'items.category', 'media');
        $this->loadReferenceItems();
    }

    public function acceptAllAction(): Action
    {
        return Action::make('acceptAll')
            ->label('Accept All Items')
            ->color('success')
            ->icon('heroicon-o-check-badge')
            ->button()
            ->requiresConfirmation()
            ->modalHeading('Accept All Items & Review Sync Flags')
            ->modalDescription(function () {
                $this->refreshAuditRelations();
                $newItems = $this->audit->items->filter(fn ($item) => !empty($item->snapshot_data['is_new']));

                if ($newItems->isEmpty()) {
                    return new \Illuminate\Support\HtmlString('Are you sure you want to accept all remaining items in this audit? No new items were added by the inspector.');
                }

                $html = '<div style="display: flex; flex-direction: column; gap: 0.75rem; margin-top: 0.5rem; text-align: left;">';
                $html .= '<p style="margin: 0; font-weight: 500; font-size: 0.875rem; color: #374151;">Review the items added by the inspector and their property sync flags before accepting:</p>';
                $html .= '<div style="max-height: 220px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 0.375rem; padding: 0.5rem; background: #f9fafb; display: flex; flex-direction: column; gap: 0.5rem;">';

                foreach ($newItems as $item) {
                    $isExcluded = !empty($item->snapshot_data['exclude_from_sync']);
                    $syncStatus = $isExcluded 
                        ? '<span style="background: #f3f4f6; color: #4b5563; padding: 0.125rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600; border: 1px solid #d1d5db;">🚫 Excluded</span>'
                        : '<span style="background: #dcfce7; color: #15803d; padding: 0.125rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600; border: 1px solid #bbf7d0;">⚡ Will Sync</span>';

                    $categoryName = e($item->category?->name ?? 'General');
                    $itemName = e($item->name);

                    $html .= "<div style=\"display: flex; align-items: center; justify-content: space-between; font-size: 0.875rem; padding: 0.5rem; background: white; border-radius: 0.375rem; border: 1px solid #e5e7eb; gap: 0.5rem;\">";
                    $html .= "<div><strong>{$itemName}</strong> <span style=\"color: #6b7280; font-size: 0.75rem;\">({$categoryName})</span></div>";
                    $html .= "<div>{$syncStatus}</div>";
                    $html .= "</div>";
                }

                $html .= '</div></div>';

                return new \Illuminate\Support\HtmlString($html);
            })
            ->visible(fn () => $this->audit->canReview())
            ->action(function () {
                app(\App\Domain\Audit\Services\AuditReviewService::class)->acceptAllItems($this->audit, auth()->user());
                $this->refreshAuditRelations();
                \Filament\Notifications\Notification::make()
                    ->title('All items accepted successfully.')
                    ->success()
                    ->send();
            });
    }

    public function requestChangesAction(): Action
    {
        return Action::make('requestChanges')
            ->label('Request Changes')
            ->color('danger')
            ->icon('heroicon-o-arrow-uturn-left')
            ->button()
            ->requiresConfirmation()
            ->modalHeading('Send Back to Inspector')
            ->modalDescription('Are you sure you want to send this audit back to the inspector for updates or additional items?')
            ->form([
                Textarea::make('notes')
                    ->label('Notes for Inspector (Optional)')
                    ->placeholder('Specify any additional details or items required...'),
            ])
            ->visible(fn () => $this->audit->canRequestChanges())
            ->action(function (array $data) {
                if (!empty($data['notes'])) {
                    $this->audit->update(['notes' => $data['notes']]);
                }
                app(\App\Domain\Audit\Services\AuditReviewService::class)->requestChanges($this->audit);
                $this->refreshAuditRelations();
                \Filament\Notifications\Notification::make()
                    ->title('Audit sent back to inspector')
                    ->success()
                    ->send();
            });
    }

    public function reopenAuditAction(): Action
    {
        return Action::make('reopenAudit')
            ->label('Reopen Audit')
            ->color('warning')
            ->icon('heroicon-o-arrow-path')
            ->button()
            ->requiresConfirmation()
            ->modalHeading('Reopen Approved Audit')
            ->modalDescription('Are you sure you want to reopen this approved audit? It will return to in-review status so findings can be updated.')
            ->visible(fn () => $this->audit->canReopen())
            ->action(function () {
                app(\App\Domain\Audit\Services\AuditReviewService::class)->reopenAudit($this->audit, auth()->user());
                $this->refreshAuditRelations();
                \Filament\Notifications\Notification::make()
                    ->title('Audit reopened successfully')
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
                    $this->refreshAuditRelations();
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
                    $this->refreshAuditRelations();
                }
            });
    }

    public function resetItemAction(): Action
    {
        return Action::make('resetItem')
            ->label('Reset Decision')
            ->color('gray')
            ->icon('heroicon-o-arrow-path')
            ->button()
            ->action(function (array $arguments) {
                $item = AuditItem::find($arguments['item_id']);
                if ($item) {
                    app(\App\Domain\Audit\Services\AuditReviewService::class)->resetItem($item, auth()->user());
                    $this->refreshAuditRelations();
                }
            });
    }

    public function toggleExcludeFromSyncAction(): Action
    {
        return Action::make('toggleExcludeFromSync')
            ->label(function (array $arguments) {
                $item = AuditItem::find($arguments['item_id'] ?? null);
                return !empty($item?->snapshot_data['exclude_from_sync']) ? 'Include in Sync' : 'Exclude from Sync';
            })
            ->color(function (array $arguments) {
                $item = AuditItem::find($arguments['item_id'] ?? null);
                return !empty($item?->snapshot_data['exclude_from_sync']) ? 'success' : 'warning';
            })
            ->icon(function (array $arguments) {
                $item = AuditItem::find($arguments['item_id'] ?? null);
                return !empty($item?->snapshot_data['exclude_from_sync']) ? 'heroicon-o-check-circle' : 'heroicon-o-minus-circle';
            })
            ->button()
            ->action(function (array $arguments) {
                $item = AuditItem::find($arguments['item_id']);
                if ($item) {
                    $snapshot = $item->snapshot_data ?? [];
                    $snapshot['exclude_from_sync'] = empty($snapshot['exclude_from_sync']);
                    $item->update(['snapshot_data' => $snapshot]);
                    $this->refreshAuditRelations();

                    $msg = !empty($snapshot['exclude_from_sync'])
                        ? "Item '{$item->name}' will be excluded from property sync."
                        : "Item '{$item->name}' will be included in property sync.";

                    \Filament\Notifications\Notification::make()
                        ->title($msg)
                        ->info()
                        ->send();
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
