<?php

namespace App\Domain\Agreement\Services;

use App\Domain\Agreement\Models\TenancyAgreement;
use Barryvdh\DomPDF\Facade\Pdf;
use NumberFormatter;

class TenancyAgreementPdfService
{
    public function generatePdf(TenancyAgreement $agreement): string
    {
        $agreement->load([
            'property',
            'property.owner',
            'property.rooms.roomDefinition',
            'property.inventories.inventoryType',
            'audit',
            'audit.categories.items.source',
            'roles.party',
            'roles.party.individual',
            'roles.party.addresses',
        ]);

        $owner = $agreement->property?->owner;
        $primaryRole = $agreement->roles->where('is_primary', true)->first();
        $tenant = $primaryRole?->party;

        $ownerAddress = $owner?->addresses->where('is_primary', true)->first()?->address_line_1 
            ?? $owner?->addresses->first()?->address_line_1 
            ?? '';

        $tenantAddress = $tenant?->addresses->where('is_primary', true)->first()?->address_line_1 
            ?? $tenant?->addresses->first()?->address_line_1 
            ?? '';

        $propertyAddress = $agreement->property?->address 
            ?? $agreement->property?->location 
            ?? '';

        $rentInWords = $this->numberToWords((int) $agreement->rent_amount);
        $depositInWords = $this->numberToWords((int) $agreement->security_deposit);

        $tenantBankDetails = $agreement->tenant_bank_details ?? [];

        $mou = $agreement->property?->mous()?->latest()->first();
        $annexure1BankDetails = [
            'beneficiary_name' => $mou?->bank_details['beneficiary_name'] ?? $mou?->bank_details['account_holder_name'] ?? 'ASSAM ALAY',
            'bank_name' => $mou?->bank_details['bank_name'] ?? 'IndusInd Bank',
            'bank_address' => $mou?->bank_details['bank_address'] ?? 'Beltola, Guwahati',
            'account_number' => $mou?->bank_details['account_number'] ?? '201025429005',
            'account_type' => $mou?->bank_details['account_type'] ?? 'Current',
            'ifsc_code' => $mou?->bank_details['ifsc_code'] ?? 'INDB0000662',
        ];

        $auditCategories = $agreement->audit ? $agreement->audit->categories : collect();
        $roomGroupedItems = $this->organizeAuditByRoom($agreement);

        $pdf = Pdf::loadView('pdf.tenancy_agreement', [
            'agreement' => $agreement,
            'property' => $agreement->property,
            'owner' => $owner,
            'tenant' => $tenant,
            'ownerAddress' => $ownerAddress,
            'tenantAddress' => $tenantAddress,
            'propertyAddress' => $propertyAddress,
            'rentInWords' => $rentInWords,
            'depositInWords' => $depositInWords,
            'annexure1BankDetails' => $annexure1BankDetails,
            'tenantBankDetails' => $tenantBankDetails,
            'audit' => $agreement->audit,
            'auditCategories' => $auditCategories,
            'roomGroupedItems' => $roomGroupedItems,
        ]);

        return $pdf->output();
    }

    public function saveDraftPdf(TenancyAgreement $agreement): void
    {
        $binary = $this->generatePdf($agreement);
        $filename = 'Tenancy_Agreement_Draft_' . ($agreement->code ?? $agreement->id) . '.pdf';
        
        $tempPath = sys_get_temp_dir() . '/' . $filename;
        file_put_contents($tempPath, $binary);

        $agreement->clearMediaCollection('draft_pdf');
        $agreement->addMedia($tempPath)->toMediaCollection('draft_pdf');
    }

    public function organizeAuditByRoom(TenancyAgreement $agreement): array
    {
        $audit = $agreement->audit;
        if (!$audit || !$audit->categories || $audit->categories->isEmpty()) {
            return [];
        }

        $allCategories = $audit->categories;
        
        $categoryNames = $allCategories->pluck('name')->map(fn($n) => strtolower(trim($n)))->toArray();
        $hasStandardCategories = in_array('rooms', $categoryNames) || in_array('inventory', $categoryNames);

        if (!$hasStandardCategories) {
            $grouped = [];
            foreach ($allCategories as $category) {
                $grouped[] = [
                    'room_name' => $category->name,
                    'room_item' => null,
                    'items' => $category->items->all(),
                ];
            }
            return $grouped;
        }

        $allItems = $allCategories->flatMap->items;
        $roomMap = [];
        $grouped = [];

        // Identify Room items
        $roomCategory = $allCategories->firstWhere(fn($c) => strtolower(trim($c->name)) === 'rooms');
        $roomItems = $roomCategory ? $roomCategory->items : $allItems->filter(function ($item) {
            $stagedType = $item->snapshot_data['staged_type'] ?? null;
            return $item->source_type === \App\Domain\Property\Models\PropertyRoom::class || $stagedType === 'room';
        });

        foreach ($roomItems as $roomItem) {
            $roomName = $roomItem->name;
            $roomKey = 'room_' . ($roomItem->source_id ?? $roomItem->id);

            $grouped[$roomKey] = [
                'room_name' => $roomName,
                'room_item' => $roomItem,
                'items' => [],
            ];

            if ($roomItem->source_id) {
                $roomMap['prop_room_' . $roomItem->source_id] = $roomKey;
            }
            $roomMap['audit_item_' . $roomItem->id] = $roomKey;
            $roomMap['name_' . strtolower(trim($roomName))] = $roomKey;
        }

        if ($agreement->property && $agreement->property->rooms) {
            foreach ($agreement->property->rooms as $pRoom) {
                $name = $pRoom->custom_name ?: ($pRoom->roomDefinition->name ?? 'Room');
                $matchedKey = 'name_' . strtolower(trim($name));
                if (isset($roomMap[$matchedKey])) {
                    $roomMap['prop_room_' . $pRoom->id] = $roomMap[$matchedKey];
                } else {
                    $roomKey = 'prop_room_' . $pRoom->id;
                    if (!isset($grouped[$roomKey])) {
                        $grouped[$roomKey] = [
                            'room_name' => $name,
                            'room_item' => null,
                            'items' => [],
                        ];
                        $roomMap['prop_room_' . $pRoom->id] = $roomKey;
                        $roomMap['name_' . strtolower(trim($name))] = $roomKey;
                    }
                }
            }
        }

        // Assign Inventory & Other items to Rooms
        $nonRoomItems = $allItems->reject(fn($item) => $roomItems->contains('id', $item->id));

        foreach ($nonRoomItems as $item) {
            $assignedRoomKey = null;

            $propRoomId = $item->snapshot_data['room_id'] 
                ?? $item->snapshot_data['property_room_id'] 
                ?? ($item->source instanceof \App\Domain\Property\Models\PropertyInventory ? $item->source->property_room_id : null);

            if ($propRoomId && isset($roomMap['prop_room_' . $propRoomId])) {
                $assignedRoomKey = $roomMap['prop_room_' . $propRoomId];
            }

            if (!$assignedRoomKey && !empty($item->snapshot_data['staged_room_item_id'])) {
                $stagedId = $item->snapshot_data['staged_room_item_id'];
                if (isset($roomMap['audit_item_' . $stagedId])) {
                    $assignedRoomKey = $roomMap['audit_item_' . $stagedId];
                }
            }

            if (!$assignedRoomKey && preg_match('/\(([^)]+)\)$/', $item->name, $matches)) {
                $extractedRoomName = strtolower(trim($matches[1]));
                if (isset($roomMap['name_' . $extractedRoomName])) {
                    $assignedRoomKey = $roomMap['name_' . $extractedRoomName];
                }
            }

            $clonedItem = clone $item;
            if ($assignedRoomKey && isset($grouped[$assignedRoomKey])) {
                $clonedItem->display_name = trim(preg_replace('/\s*\([^)]+\)$/', '', $item->name));
                $grouped[$assignedRoomKey]['items'][] = $clonedItem;
            } else {
                $generalKey = 'general';
                if (!isset($grouped[$generalKey])) {
                    $grouped[$generalKey] = [
                        'room_name' => 'General Premises & Utilities',
                        'room_item' => null,
                        'items' => [],
                    ];
                }
                $clonedItem->display_name = $item->name;
                $grouped[$generalKey]['items'][] = $clonedItem;
            }
        }

        return array_values(array_filter($grouped, fn($g) => !empty($g['room_item']) || !empty($g['items'])));
    }

    private function numberToWords(int $number): string
    {
        if (class_exists('NumberFormatter')) {
            $formatter = new NumberFormatter('en_IN', NumberFormatter::SPELLOUT);
            return ucwords($formatter->format($number));
        }
        return (string) $number;
    }
}
