@php
    /** @var \App\Domain\Maintenance\Models\MaintenanceClientQuote|null $record */
    $ticket = $record?->maintenanceRequest;
    $quotes = $ticket ? \App\Domain\Maintenance\Models\MaintenanceVendorQuote::where('maintenance_request_id', $ticket->id)
        ->with(['vendor', 'vendor.vendorProfile.trade', 'media'])
        ->get() : collect();

    $includedIds = $record ? (array) $record->getIncludedVendorQuoteIds() : [];
    $awardedIds = $record ? (array) ($record->awarded_vendor_quote_ids ?? []) : [];
    $isApproved = ($record?->status === 'approved');
    $isArchived = ($record?->status === 'archived');
    $hasAwardedOrders = ! empty($awardedIds);
    $canSelect = ($isApproved && ! $hasAwardedOrders && ! $isArchived);
@endphp

<div style="width: 100%; display: flex; flex-direction: column; gap: 16px;">
    {{-- Gate Check Banner --}}
    @if($isArchived)
        <div style="padding: 14px 18px; border-radius: 10px; background-color: #f8fafc; border: 1px solid #e2e8f0; font-size: 13px; color: #475569; display: flex; align-items: center; gap: 12px;">
            <x-filament::icon icon="heroicon-o-lock-closed" style="width: 20px; height: 20px; color: #64748b; flex-shrink: 0;" />
            <div>
                <strong style="color: #0f172a; font-weight: 700;">Quotation Archived:</strong>
                Work orders cannot be modified or issued for an archived quotation.
            </div>
        </div>
    @elseif(! $isApproved)
        <div style="padding: 14px 18px; border-radius: 10px; background-color: #fffbeb; border: 1px solid #fde68a; font-size: 13px; color: #92400e; display: flex; align-items: center; gap: 12px;">
            <x-filament::icon icon="heroicon-o-lock-closed" style="width: 20px; height: 20px; color: #d97706; flex-shrink: 0;" />
            <div>
                <strong style="color: #78350f; font-weight: 700;">🔒 Work Orders Locked:</strong>
                Client quotation must be approved in <strong style="text-decoration: underline;">Tab 3 (Client Approval)</strong> before contractor work orders can be awarded.
            </div>
        </div>
    @endif

    @if($quotes->isEmpty())
        <div style="padding: 48px 24px; text-align: center; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px;">
            <x-filament::icon icon="heroicon-o-document-text" style="width: 36px; height: 36px; margin: 0 auto 12px; color: #94a3b8;" />
            <div style="font-weight: 700; font-size: 15px; color: #1e293b;">No Contractor Estimates Found</div>
            <div style="font-size: 13px; color: #64748b; margin-top: 4px;">Please record contractor estimates in Tab 1 (Vendor Estimates) first.</div>
        </div>
    @else
        {{-- Column Headers Bar --}}
        <div style="display: grid; grid-template-columns: 48px 2.2fr 1.6fr 1.1fr 1.5fr 1.2fr; gap: 14px; padding: 10px 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; align-items: center;">
            <div style="text-align: center;">Award</div>
            <div>Trade Scope / Work Title</div>
            <div>Contractor / Artisan</div>
            <div style="text-align: right;">Quoted Cost</div>
            <div style="text-align: center;">Client Quote Basis</div>
            <div style="text-align: right;">Work Order Status</div>
        </div>

        {{-- Cards List with generous inter-row spacing --}}
        <div style="display: flex; flex-direction: column; gap: 12px;">
            @foreach($quotes as $q)
                @php
                    $qid = (string) $q->id;
                    $isIncluded = in_array($qid, $includedIds);
                    $isAwarded = in_array($qid, $awardedIds);
                    $vendorName = $q->vendor?->display_name ?? 'Contractor';
                    $tradeName = $q->vendor?->vendorProfile?->trade?->name ?? 'General';
                    $phone = $q->vendor?->phone;
                    $cost = number_format((float) $q->quoted_cost, 2);
                    $quoteSuffix = strtoupper(substr(str_replace(['QT-', 'QTE-'], '', $record?->quote_number ?: (string) $record?->id), -5));
                    $woNumber = $q->work_order_number ?? ($isAwarded ? ("WO-" . now()->year . "-{$quoteSuffix}-01") : null);

                    $downloadUrl = null;
                    $streamUrl = null;
                    if ($isAwarded) {
                        $downloadUrl = route('billing.work_order.pdf.download', ['vendorQuote' => $q->id]);
                        $streamUrl = route('billing.work_order.pdf', ['vendorQuote' => $q->id]);
                    }

                    // Card Styling
                    if ($isAwarded) {
                        $cardBg = '#eff6ff';
                        $cardBorder = '1px solid #bfdbfe';
                        $cardAccent = 'border-left: 6px solid #2563eb;';
                    } elseif ($isIncluded) {
                        $cardBg = '#f0fdf4';
                        $cardBorder = '1px solid #bbf7d0';
                        $cardAccent = 'border-left: 6px solid #16a34a;';
                    } else {
                        $cardBg = '#ffffff';
                        $cardBorder = '1px solid #e2e8f0';
                        $cardAccent = 'border-left: 6px solid #cbd5e1;';
                    }
                @endphp

                <div style="background-color: {{ $cardBg }}; border: {{ $cardBorder }}; {{ $cardAccent }} border-radius: 12px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); display: grid; grid-template-columns: 48px 2.2fr 1.6fr 1.1fr 1.5fr 1.2fr; gap: 14px; align-items: center;">
                    {{-- Checkbox --}}
                    <div style="display: flex; justify-content: center; align-items: center;">
                        @if($isAwarded)
                            <span style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 6px; background-color: #2563eb; color: #ffffff;" title="Work order already issued">
                                <x-filament::icon icon="heroicon-m-check" style="width: 16px; height: 16px;" />
                            </span>
                        @else
                            <input type="checkbox"
                                   wire:model.live="data.awarded_vendor_quote_ids"
                                   value="{{ $qid }}"
                                   {{ ! $canSelect ? 'disabled' : '' }}
                                   style="width: 20px; height: 20px; border-radius: 5px; accent-color: #2563eb; cursor: {{ $canSelect ? 'pointer' : 'not-allowed' }}; opacity: {{ $canSelect ? '1' : '0.4' }};" />
                        @endif
                    </div>

                    {{-- Trade Scope / Ref --}}
                    <div>
                        <div style="font-weight: 700; font-size: 14px; color: #0f172a; display: flex; align-items: center; gap: 6px; line-height: 1.4;">
                            @if($isIncluded)
                                <span style="color: #eab308; font-size: 15px;" title="Included in approved customer quotation">⭐</span>
                            @endif
                            <span>{{ $q->trade_title }}</span>
                        </div>
                        @if($q->vendor_quote_number)
                            <div style="margin-top: 4px; display: flex; align-items: center; gap: 6px; font-size: 12px; color: #64748b;">
                                <span>Ref:</span>
                                <span style="font-family: monospace; background-color: rgba(0,0,0,0.05); padding: 1px 6px; border-radius: 4px; font-weight: 600; color: #334155;">{{ $q->vendor_quote_number }}</span>
                            </div>
                        @endif
                    </div>

                    {{-- Contractor & Trade --}}
                    <div>
                        <div style="font-weight: 600; font-size: 14px; color: #1e293b;">
                            {{ $vendorName }}
                        </div>
                        <div style="margin-top: 4px; display: flex; align-items: center; gap: 8px; font-size: 12px; color: #64748b;">
                            <span style="display: inline-block; padding: 2px 7px; border-radius: 4px; background-color: rgba(0,0,0,0.05); font-size: 11px; font-weight: 600; color: #475569;">
                                {{ $tradeName }}
                            </span>
                            @if($phone)
                                <span style="font-family: monospace; font-size: 11px; color: #64748b;">📞 {{ $phone }}</span>
                            @endif
                        </div>
                    </div>

                    {{-- Quoted Cost --}}
                    <div style="text-align: right;">
                        <div style="font-weight: 800; font-size: 16px; color: #0f172a; letter-spacing: -0.02em;">
                            ₹{{ $cost }}
                        </div>
                        <div style="font-size: 11px; color: #94a3b8; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.04em;">
                            Contractor Bid
                        </div>
                    </div>

                    {{-- Client Quote Basis --}}
                    <div style="text-align: center;">
                        @if($isIncluded)
                            <span style="display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border-radius: 9999px; font-size: 12px; font-weight: 700; background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0;">
                                ⭐ Included in Client Quote
                            </span>
                        @else
                            <span style="display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 9999px; font-size: 11px; font-weight: 500; background-color: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0;">
                                Alternative Estimate
                            </span>
                        @endif
                    </div>

                    {{-- Work Order Status & Action --}}
                    <div style="text-align: right;">
                        @if($isAwarded)
                            <div style="display: inline-flex; align-items: center; gap: 6px;">
                                <span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; background-color: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe;">
                                    🏆 {{ $woNumber }}
                                </span>
                                @if($downloadUrl)
                                    <a href="{{ $streamUrl }}"
                                       target="_blank"
                                       style="display: inline-flex; align-items: center; justify-content: center; padding: 5px 8px; border-radius: 6px; background-color: #f1f5f9; color: #475569; text-decoration: none; font-size: 11px; font-weight: 600;"
                                       title="View Work Order PDF in New Tab">
                                        <x-filament::icon icon="heroicon-m-eye" style="width: 14px; height: 14px;" />
                                    </a>
                                    <a href="{{ $downloadUrl }}"
                                       download
                                       style="display: inline-flex; align-items: center; justify-content: center; padding: 5px 8px; border-radius: 6px; background-color: #2563eb; color: #ffffff; text-decoration: none; font-size: 11px; font-weight: 600;"
                                       title="Download Work Order PDF">
                                        <x-filament::icon icon="heroicon-m-arrow-down-tray" style="width: 14px; height: 14px;" />
                                    </a>
                                @endif
                            </div>
                        @else
                            <span style="display: inline-block; padding: 4px 8px; border-radius: 6px; font-size: 11px; color: #64748b; background-color: rgba(0,0,0,0.04);">
                                Pending Award
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Footer Summary --}}
        <div style="margin-top: 4px; padding: 12px 18px; border-radius: 10px; background-color: #f8fafc; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; font-size: 12px; color: #64748b;">
            <div style="display: flex; align-items: center; gap: 6px;">
                <span>💡 Check contractor estimate(s) above, then click <strong style="color: #0f172a;">"Issue Work Order(s)"</strong> in the header to authorize.</span>
            </div>
            <div style="display: flex; align-items: center; gap: 16px;">
                <div>Total Estimates: <strong style="color: #0f172a; font-weight: 700;">{{ $quotes->count() }}</strong></div>
                <div style="color: #cbd5e1;">•</div>
                <div>Approved in Scope: <strong style="color: #16a34a; font-weight: 700;">{{ count($includedIds) }}</strong></div>
            </div>
        </div>
    @endif
</div>
