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

<div style="display: flex; flex-direction: column; gap: 1rem; width: 100%;">
    {{-- Gate Check Banner --}}
    @if($isArchived)
        <div style="padding: 0.875rem 1rem; border-radius: 0.75rem; background: rgba(128, 128, 128, 0.05); border: 1px solid rgba(128, 128, 128, 0.2); font-size: 0.75rem; color: #4b5563; display: flex; align-items: center; gap: 0.75rem;">
            <x-filament::icon icon="heroicon-o-lock-closed" style="width: 1.25rem; height: 1.25rem; color: #9ca3af; flex-shrink: 0;" />
            <div>
                <strong style="font-weight: 700;">Quotation Archived:</strong>
                Work orders cannot be modified or issued for an archived quotation.
            </div>
        </div>
    @elseif(! $isApproved)
        <div style="padding: 0.875rem 1rem; border-radius: 0.75rem; background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.3); font-size: 0.75rem; color: #92400e; display: flex; align-items: center; gap: 0.75rem;">
            <x-filament::icon icon="heroicon-o-lock-closed" style="width: 1.25rem; height: 1.25rem; color: #f59e0b; flex-shrink: 0;" />
            <div>
                <strong style="font-weight: 700; color: #78350f;">🔒 Work Orders Locked:</strong>
                Client quotation must be approved in <strong style="text-decoration: underline; font-weight: 600;">Tab 3 (Client Approval)</strong> before contractor work orders can be awarded.
            </div>
        </div>
    @endif

    @if($quotes->isEmpty())
        <div style="padding: 3rem 1.5rem; text-align: center; background: rgba(128, 128, 128, 0.02); border: 1px solid rgba(128, 128, 128, 0.15); border-radius: 0.75rem;">
            <x-filament::icon icon="heroicon-o-document-text" style="width: 2.25rem; height: 2.25rem; margin: 0 auto 0.75rem auto; color: #9ca3af;" />
            <div style="font-weight: 700; font-size: 0.875rem;">No Contractor Estimates Found</div>
            <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">Please record contractor estimates in Tab 1 (Vendor Estimates) first.</div>
        </div>
    @else
        <div style="overflow-x: auto; width: 100%;">
            <div style="min-width: 750px; display: flex; flex-direction: column; gap: 0.75rem;">
                {{-- Column Headers Bar --}}
                <div style="display: grid; grid-template-columns: 48px 2.2fr 1.6fr 1.1fr 1.5fr 1.2fr; gap: 0.875rem; padding: 0.5rem 1.25rem; font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; align-items: center;">
                    <div style="text-align: center;">Award</div>
                    <div>Trade Scope / Work Title</div>
                    <div>Contractor / Artisan</div>
                    <div style="text-align: right;">Quoted Cost</div>
                    <div style="text-align: center;">Client Quote Basis</div>
                    <div style="text-align: right;">Work Order Status</div>
                </div>

                {{-- Cards List with generous inter-row spacing --}}
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
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

                            if ($isAwarded) {
                                $cardStyle = 'background-color: rgba(37, 99, 235, 0.06); border: 1px solid rgba(37, 99, 235, 0.25); border-left: 4px solid #2563eb;';
                            } elseif ($isIncluded) {
                                $cardStyle = 'background-color: rgba(16, 185, 129, 0.06); border: 1px solid rgba(16, 185, 129, 0.25); border-left: 4px solid #10b981;';
                            } else {
                                $cardStyle = 'background-color: rgba(128, 128, 128, 0.02); border: 1px solid rgba(128, 128, 128, 0.18); border-left: 4px solid #cbd5e1;';
                            }
                        @endphp

                        <div style="{{ $cardStyle }} border-radius: 0.75rem; padding: 1rem 1.125rem; display: grid; grid-template-columns: 48px 2.2fr 1.6fr 1.1fr 1.5fr 1.2fr; gap: 0.875rem; align-items: center;">
                            {{-- Checkbox --}}
                            <div style="display: flex; justify-content: center; align-items: center;">
                                @if($isAwarded)
                                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 1.625rem; height: 1.625rem; border-radius: 0.375rem; background-color: #2563eb; color: #ffffff;" title="Work order already issued">
                                        <x-filament::icon icon="heroicon-m-check" style="width: 1rem; height: 1rem;" />
                                    </span>
                                @else
                                    <input type="checkbox"
                                           wire:model.live="data.awarded_vendor_quote_ids"
                                           value="{{ $qid }}"
                                           {{ ! $canSelect ? 'disabled' : '' }}
                                           style="width: 1.25rem; height: 1.25rem; border-radius: 0.375rem; cursor: {{ $canSelect ? 'pointer' : 'not-allowed' }}; opacity: {{ $canSelect ? '1' : '0.4' }};" />
                                @endif
                            </div>

                            {{-- Trade Scope / Ref --}}
                            <div>
                                <div style="font-weight: 700; font-size: 0.875rem; display: flex; align-items: center; gap: 0.375rem; line-height: 1.35;">
                                    @if($isIncluded)
                                        <span style="color: #f59e0b; font-size: 1rem;" title="Included in approved customer quotation">⭐</span>
                                    @endif
                                    <span>{{ $q->trade_title }}</span>
                                </div>
                                @if($q->vendor_quote_number)
                                    <div style="margin-top: 0.25rem; display: flex; align-items: center; gap: 0.375rem; font-size: 0.75rem; color: #6b7280;">
                                        <span>Ref:</span>
                                        <span style="font-family: monospace; background: rgba(128, 128, 128, 0.1); padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.6875rem; font-weight: 600;">{{ $q->vendor_quote_number }}</span>
                                    </div>
                                @endif
                            </div>

                            {{-- Contractor & Trade --}}
                            <div>
                                <div style="font-weight: 600; font-size: 0.875rem;">
                                    {{ $vendorName }}
                                </div>
                                <div style="margin-top: 0.25rem; display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; color: #6b7280; flex-wrap: wrap;">
                                    <span style="display: inline-block; padding: 0.125rem 0.375rem; border-radius: 0.25rem; background: rgba(128, 128, 128, 0.1); font-size: 0.6875rem; font-weight: 600;">
                                        {{ $tradeName }}
                                    </span>
                                    @if($phone)
                                        <span style="font-family: monospace; font-size: 0.6875rem; color: #6b7280;">📞 {{ $phone }}</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Quoted Cost --}}
                            <div style="text-align: right;">
                                <div style="font-weight: 800; font-size: 1rem; letter-spacing: -0.02em;">
                                    ₹{{ $cost }}
                                </div>
                                <div style="font-size: 0.6875rem; color: #9ca3af; margin-top: 0.125rem; text-transform: uppercase; letter-spacing: 0.05em;">
                                    Contractor Bid
                                </div>
                            </div>

                            {{-- Client Quote Basis --}}
                            <div style="text-align: center;">
                                @if($isIncluded)
                                    <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; background-color: rgba(16, 185, 129, 0.15); color: #047857; border: 1px solid rgba(16, 185, 129, 0.3);">
                                        ⭐ Included in Client Quote
                                    </span>
                                @else
                                    <span style="display: inline-flex; align-items: center; padding: 0.125rem 0.625rem; border-radius: 9999px; font-size: 0.6875rem; font-weight: 500; background-color: rgba(128, 128, 128, 0.1); color: #6b7280; border: 1px solid rgba(128, 128, 128, 0.15);">
                                        Alternative Estimate
                                    </span>
                                @endif
                            </div>

                            {{-- Work Order Status & Action --}}
                            <div style="text-align: right;">
                                @if($isAwarded)
                                    <div style="display: inline-flex; align-items: center; gap: 0.375rem;">
                                        <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.6875rem; font-weight: 700; background-color: rgba(37, 99, 235, 0.15); color: #1e40af; border: 1px solid rgba(37, 99, 235, 0.3);">
                                            🏆 {{ $woNumber }}
                                        </span>
                                        @if($downloadUrl)
                                            <button type="button"
                                                    wire:click.prevent="mountAction('viewWorkOrderPdf', { vendorQuoteId: '{{ $q->id }}', title: '{{ addslashes(($woNumber ?: 'Work Order') . ' – ' . $vendorName . ' (' . $tradeName . ')') }}' })"
                                                    style="display: inline-flex; align-items: center; justify-content: center; padding: 0.375rem; border-radius: 0.375rem; background: rgba(128, 128, 128, 0.1); color: #4b5563; border: none; cursor: pointer;"
                                                    title="View Work Order PDF">
                                                <x-filament::icon icon="heroicon-m-eye" style="width: 0.875rem; height: 0.875rem;" />
                                            </button>
                                            <a href="{{ $downloadUrl }}"
                                               download
                                               style="display: inline-flex; align-items: center; justify-content: center; padding: 0.375rem; border-radius: 0.375rem; background-color: #2563eb; color: #ffffff; text-decoration: none;"
                                               title="Download Work Order PDF">
                                                <x-filament::icon icon="heroicon-m-arrow-down-tray" style="width: 0.875rem; height: 0.875rem;" />
                                            </a>
                                        @endif
                                    </div>
                                @else
                                    <span style="display: inline-block; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.6875rem; color: #6b7280; background: rgba(128, 128, 128, 0.08);">
                                        Pending Award
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Footer Summary --}}
                <div style="margin-top: 0.25rem; padding: 0.875rem 1rem; border-radius: 0.75rem; background: rgba(128, 128, 128, 0.03); border: 1px solid rgba(128, 128, 128, 0.15); display: flex; align-items: center; justify-content: space-between; font-size: 0.75rem; color: #4b5563; flex-wrap: wrap; gap: 0.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <span>💡 Check contractor estimate(s) above, then click <strong style="font-weight: 700;">"Issue Work Order(s)"</strong> in the header to authorize.</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div>Total Estimates: <strong style="font-weight: 700;">{{ $quotes->count() }}</strong></div>
                        <div style="color: #cbd5e1;">•</div>
                        <div>Approved in Scope: <strong style="color: #10b981; font-weight: 700;">{{ count($includedIds) }}</strong></div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
