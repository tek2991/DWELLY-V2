<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Audit Inspection Report - {{ $audit->audit_number }}</title>
    <style>
        @page {
            margin: 28px 30px 35px 30px;
        }
        body {
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #1e293b;
            margin: 0;
            padding: 0;
            line-height: 1.35;
        }
        
        /* Header styling */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 8px;
        }
        .brand-title {
            font-size: 22px;
            font-weight: bold;
            color: #1e3a8a;
            letter-spacing: 0.5px;
        }
        .brand-sub {
            font-size: 9px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }
        .doc-title {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
            text-align: right;
            text-transform: uppercase;
        }
        .doc-meta {
            font-size: 9.5px;
            color: #475569;
            text-align: right;
            margin-top: 3px;
        }
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .badge-lg {
            padding: 4px 10px;
            font-size: 10px;
            border-radius: 5px;
        }
        .badge-primary { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        .badge-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .badge-warning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .badge-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-gray { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
        
        /* Info Grid */
        .info-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            margin-bottom: 12px;
        }
        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            padding: 8px 10px;
            vertical-align: top;
            width: 50%;
        }
        .box-title {
            font-size: 10px;
            font-weight: bold;
            color: #1e3a8a;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 3px;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .info-row {
            margin-bottom: 3px;
            font-size: 9.5px;
        }
        .info-label {
            color: #64748b;
            display: inline-block;
            width: 105px;
            font-weight: bold;
        }
        .info-val {
            color: #0f172a;
        }

        /* Summary / Stats Card */
        .stats-table {
            width: 100%;
            border-collapse: collapse;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            margin-bottom: 14px;
        }
        .stats-table td {
            padding: 8px 12px;
            text-align: center;
            border-right: 1px solid #cbd5e1;
            vertical-align: middle;
        }
        .stats-table td:last-child {
            border-right: none;
        }
        .stat-value {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
        }
        .stat-label {
            font-size: 8.5px;
            color: #64748b;
            text-transform: uppercase;
            margin-top: 1px;
            font-weight: 600;
        }

        /* Section Heading */
        .section-header {
            font-size: 12px;
            font-weight: bold;
            color: #1e3a8a;
            border-bottom: 2px solid #cbd5e1;
            padding-bottom: 4px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        /* Index Table */
        .index-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #cbd5e1;
            margin-bottom: 15px;
        }
        .index-table th {
            background: #1e3a8a;
            color: #ffffff;
            font-size: 9px;
            font-weight: bold;
            padding: 6px 8px;
            border: 1px solid #1e3a8a;
            text-align: left;
            text-transform: uppercase;
        }
        .index-table td {
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
            font-size: 9px;
        }
        .index-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        /* Dedicated Item Page */
        .item-page {
            page-break-before: always;
            padding-top: 5px;
        }
        
        .item-banner {
            background: #1e3a8a;
            color: #ffffff;
            border-radius: 5px;
            padding: 8px 12px;
            margin-bottom: 12px;
        }
        .item-banner-top {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #93c5fd;
            margin-bottom: 2px;
        }
        .item-banner-name {
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 0.3px;
        }

        /* Item Findings Grid */
        .findings-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px 0;
            margin-bottom: 12px;
        }
        .findings-col {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            padding: 10px 12px;
            vertical-align: top;
            width: 50%;
        }

        .remarks-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-left: 4px solid #2563eb;
            border-radius: 4px;
            padding: 8px 10px;
            margin-top: 5px;
            font-size: 9.5px;
            color: #1e3a8a;
        }

        /* Prominent Photo Gallery */
        .evidence-heading {
            font-size: 11px;
            font-weight: bold;
            color: #1e3a8a;
            border-bottom: 1.5px solid #cbd5e1;
            padding-bottom: 4px;
            margin: 14px 0 10px 0;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .photo-gallery-single {
            text-align: center;
            margin: 10px 0;
        }
        .photo-gallery-single .photo-card {
            display: inline-block;
            max-width: 520px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #ffffff;
            padding: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            text-align: center;
        }
        .photo-gallery-single .photo-img {
            max-width: 500px;
            max-height: 340px;
            width: auto;
            height: auto;
            border-radius: 4px;
            display: block;
            margin: 0 auto;
        }

        .photo-gallery-dual {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px;
            margin: 8px 0;
        }
        .photo-gallery-dual td {
            width: 50%;
            vertical-align: top;
            text-align: center;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #ffffff;
            padding: 6px;
        }
        .photo-gallery-dual .photo-img {
            width: 100%;
            max-height: 240px;
            object-fit: contain;
            border-radius: 4px;
            display: block;
            margin: 0 auto;
        }

        .photo-gallery-multi {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            margin: 6px 0;
        }
        .photo-gallery-multi td {
            width: 33.33%;
            vertical-align: top;
            text-align: center;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            background: #ffffff;
            padding: 5px;
        }
        .photo-gallery-multi .photo-img {
            width: 100%;
            max-height: 160px;
            object-fit: contain;
            border-radius: 3px;
            display: block;
            margin: 0 auto;
        }

        .photo-meta {
            font-size: 8.5px;
            color: #475569;
            margin-top: 4px;
            font-weight: 500;
        }

        /* Annotation Observations Breakdown Box */
        .annotation-remarks-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 3px solid #4f46e5;
            border-radius: 4px;
            padding: 6px 8px;
            margin-top: 6px;
            text-align: left;
        }
        .annotation-remarks-title {
            font-size: 8px;
            font-weight: bold;
            color: #3730a3;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 3px;
        }
        .annotation-remarks-table {
            width: 100%;
            border-collapse: collapse;
        }
        .annotation-remarks-table td {
            padding: 2px 3px;
            font-size: 8.5px;
            border: none;
            vertical-align: top;
        }
        .annotation-num-badge {
            display: inline-block;
            background: #4f46e5;
            color: #ffffff;
            font-size: 7.5px;
            font-weight: bold;
            padding: 1px 4px;
            border-radius: 3px;
            margin-right: 2px;
        }
        .annotation-type-tag {
            display: inline-block;
            background: #e0e7ff;
            color: #3730a3;
            font-size: 7.5px;
            font-weight: 600;
            padding: 1px 4px;
            border-radius: 3px;
        }

        .empty-evidence-box {
            text-align: center;
            color: #94a3b8;
            padding: 25px 15px;
            border: 1.5px dashed #cbd5e1;
            border-radius: 6px;
            background: #f8fafc;
            margin: 10px 0;
            font-size: 9.5px;
            font-style: italic;
        }

        /* Sign-off Section */
        .signoff-wrapper {
            margin-top: 20px;
            page-break-inside: avoid;
        }
        .signoff-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 12px 0;
            margin-top: 8px;
        }
        .signoff-box {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            border-radius: 5px;
            padding: 10px 12px;
            width: 50%;
            vertical-align: top;
        }
        .sign-line {
            border-bottom: 1px dashed #94a3b8;
            height: 35px;
            margin-bottom: 6px;
        }

        /* Footer */
        .footer {
            position: fixed;
            bottom: -22px;
            left: 0;
            right: 0;
            height: 18px;
            font-size: 8px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }
        .page-number:before {
            content: "Page " counter(page);
        }
    </style>
</head>
<body>
    <!-- Running Footer on all pages -->
    <div class="footer">
        <table style="width: 100%; border: none;">
            <tr>
                <td style="text-align: left; color: #64748b;">DWELLY Property Management &bull; Inspection Report #{{ $audit->audit_number }}</td>
                <td style="text-align: right; color: #64748b;">Generated on {{ $generatedAt }} &bull; <span class="page-number"></span></td>
            </tr>
        </table>
    </div>

    <!-- ========================================== -->
    <!-- PAGE 1: COVER & INSPECTION INDEX TABLE    -->
    <!-- ========================================== -->
    <div class="cover-page">
        <!-- Header -->
        <table class="header-table">
            <tr>
                <td style="width: 55%; vertical-align: top;">
                    <div class="brand-title">DWELLY</div>
                    <div class="brand-sub">Property Operations &amp; Inspection Management</div>
                </td>
                <td style="width: 45%; vertical-align: top; text-align: right;">
                    <div class="doc-title">Inspection Report</div>
                    <div class="doc-meta">
                        <strong>Audit #:</strong> {{ $audit->audit_number }}<br>
                        <strong>Type:</strong> <span class="badge badge-primary">{{ $audit->audit_type?->getLabel() ?? 'Audit' }}</span> &nbsp;
                        <strong>Status:</strong> 
                        @php
                            $statusClass = match($audit->status?->value) {
                                'approved', 'completed' => 'badge-success',
                                'in_progress', 'partially_approved' => 'badge-warning',
                                'in_review', 'pending_review' => 'badge-primary',
                                default => 'badge-gray'
                            };
                        @endphp
                        <span class="badge {{ $statusClass }}">{{ $audit->status?->getLabel() ?? 'Draft' }}</span>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Property & Audit Info Grid -->
        <table class="info-grid">
            <tr>
                <td class="info-box">
                    <div class="box-title">Property Information</div>
                    <div class="info-row">
                        <span class="info-label">Building / Unit:</span>
                        <span class="info-val"><strong>{{ $property?->building_name ?? 'N/A' }}</strong></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Property Code:</span>
                        <span class="info-val">{{ $property?->code ?? '-' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Address:</span>
                        <span class="info-val">{{ $property?->address_line_1 ?? $property?->locality ?? '-' }}{{ $property?->city ? ', ' . $property->city : '' }}</span>
                    </div>
                    @if($tenant)
                        <div class="info-row">
                            <span class="info-label">Linked Tenant:</span>
                            <span class="info-val"><strong>{{ $tenant->display_name }}</strong> ({{ $tenant->phone ?? '-' }})</span>
                        </div>
                    @endif
                </td>
                <td class="info-box">
                    <div class="box-title">Inspection Metadata</div>
                    <div class="info-row">
                        <span class="info-label">Assigned Inspector:</span>
                        <span class="info-val"><strong>{{ $inspector?->name ?? 'Unassigned' }}</strong></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Assigned Reviewer:</span>
                        <span class="info-val"><strong>{{ $reviewer?->name ?? 'Unassigned' }}</strong></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Scheduled Date:</span>
                        <span class="info-val">{{ $audit->scheduled_at ? $audit->scheduled_at->timezone(config('app.timezone', 'Asia/Kolkata'))->format('d M Y') : 'Immediate' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Completed / Approved:</span>
                        <span class="info-val">
                            {{ $audit->completed_at ? $audit->completed_at->timezone(config('app.timezone', 'Asia/Kolkata'))->format('d M Y, h:i A') : ($audit->approved_at ? $audit->approved_at->timezone(config('app.timezone', 'Asia/Kolkata'))->format('d M Y, h:i A') : ($audit->submitted_at ? $audit->submitted_at->timezone(config('app.timezone', 'Asia/Kolkata'))->format('d M Y, h:i A') : 'Pending Completion')) }}
                        </span>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Executive Summary / Stats Table -->
        <table class="stats-table">
            <tr>
                <td>
                    <div class="stat-value">{{ $totalItems }}</div>
                    <div class="stat-label">Total Items</div>
                </td>
                <td>
                    <div class="stat-value" style="color: #166534;">{{ $inspectedItems }}</div>
                    <div class="stat-label">Inspected ({{ $progress }}%)</div>
                </td>
                <td>
                    <div class="stat-value" style="color: #16a34a;">{{ $conditionCounts['excellent'] + $conditionCounts['good'] }}</div>
                    <div class="stat-label">Good / Excellent</div>
                </td>
                <td>
                    <div class="stat-value" style="color: #d97706;">{{ $conditionCounts['fair'] }}</div>
                    <div class="stat-label">Fair Condition</div>
                </td>
                <td>
                    <div class="stat-value" style="color: #dc2626;">{{ $conditionCounts['poor'] + $conditionCounts['damaged'] }}</div>
                    <div class="stat-label">Poor / Damaged</div>
                </td>
            </tr>
        </table>

        @if($audit->notes)
            <div style="background: #fffbeb; border: 1px solid #fde68a; border-left: 3px solid #d97706; padding: 6px 10px; margin-bottom: 12px; border-radius: 4px; font-size: 9px;">
                <strong style="color: #92400e;">General Audit Notes:</strong> {{ $audit->notes }}
            </div>
        @endif

        <!-- Audit Items Index Table -->
        <div class="section-header">
            Audit Items Index &bull; Summary Table
        </div>

        @if(empty($allItemsIndex))
            <div style="text-align: center; color: #64748b; padding: 20px 0; border: 1px dashed #cbd5e1; border-radius: 5px;">
                No inspection items recorded for this audit.
            </div>
        @else
            <table class="index-table">
                <thead>
                    <tr>
                        <th style="width: 5%; text-align: center;">#</th>
                        <th style="width: 22%;">Category / Room</th>
                        <th style="width: 28%;">Item Name</th>
                        <th style="width: 14%; text-align: center;">Condition</th>
                        <th style="width: 12%; text-align: center;">Status</th>
                        <th style="width: 9%; text-align: center;">Photos</th>
                        <th style="width: 10%; text-align: center;">Page Ref</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($allItemsIndex as $idxItem)
                        @php
                            $condBadgeClass = match(strtolower($idxItem['condition_label'])) {
                                'excellent' => 'badge-success',
                                'good' => 'badge-primary',
                                'fair' => 'badge-warning',
                                'poor', 'damaged', 'not working', 'missing' => 'badge-danger',
                                default => 'badge-gray'
                            };
                            $statusBadgeClass = match(strtolower($idxItem['status_label'])) {
                                'approved', 'inspected' => 'badge-success',
                                'rejected' => 'badge-danger',
                                default => 'badge-gray'
                            };
                        @endphp
                        <tr>
                            <td style="text-align: center; font-weight: bold; color: #475569;">{{ $idxItem['index'] }}</td>
                            <td><strong>{{ $idxItem['category_name'] }}</strong></td>
                            <td>
                                <strong>{{ $idxItem['item']->name }}</strong>
                                @if(!empty($idxItem['item']->snapshot_data['display_name']) && $idxItem['item']->snapshot_data['display_name'] !== $idxItem['item']->name)
                                    <div style="font-size: 8px; color: #64748b;">{{ $idxItem['item']->snapshot_data['display_name'] }}</div>
                                @endif
                            </td>
                            <td style="text-align: center;">
                                <span class="badge {{ $condBadgeClass }}">{{ $idxItem['condition_label'] }}</span>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge {{ $statusBadgeClass }}">{{ $idxItem['status_label'] }}</span>
                            </td>
                            <td style="text-align: center; font-weight: bold; color: {{ $idxItem['photos_count'] > 0 ? '#1e40af' : '#94a3b8' }};">
                                {{ $idxItem['photos_count'] > 0 ? $idxItem['photos_count'] . ' 📷' : '—' }}
                            </td>
                            <td style="text-align: center; color: #64748b; font-weight: bold;">
                                Page {{ $idxItem['index'] + 1 }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <!-- ========================================== -->
    <!-- DEDICATED PAGE PER AUDIT / INSPECTION ITEM -->
    <!-- ========================================== -->
    @foreach($allItemsIndex as $detailItem)
        @php
            $item = $detailItem['item'];
            $photos = $detailItem['photos'];
            $photosCount = count($photos);

            $condBadgeClass = match(strtolower($detailItem['condition_label'])) {
                'excellent' => 'badge-success',
                'good' => 'badge-primary',
                'fair' => 'badge-warning',
                'poor', 'damaged', 'not working', 'missing' => 'badge-danger',
                default => 'badge-gray'
            };
            $statusBadgeClass = match(strtolower($detailItem['status_label'])) {
                'approved', 'inspected' => 'badge-success',
                'rejected' => 'badge-danger',
                default => 'badge-gray'
            };
        @endphp

        <div class="item-page">
            <!-- Top Item Banner -->
            <div class="item-banner">
                <div class="item-banner-top">
                    Item {{ $detailItem['index'] }} of {{ $totalItems }} &bull; {{ strtoupper($detailItem['category_name']) }}
                </div>
                <div class="item-banner-name">
                    {{ $item->name }}
                    @if(!empty($item->snapshot_data['is_new']))
                        <span style="font-size: 9px; color: #1e3a8a; background: #fef3c7; padding: 2px 6px; border-radius: 4px; font-weight: bold; vertical-align: middle; margin-left: 6px;">
                            Added in Audit
                        </span>
                    @endif
                </div>
            </div>

            <!-- Findings & Metadata 2-Column Table -->
            <table class="findings-table">
                <tr>
                    <td class="findings-col">
                        <div class="box-title">Item Inspection State</div>
                        <div class="info-row" style="margin-top: 4px;">
                            <span class="info-label">Current Condition:</span>
                            <span class="badge badge-lg {{ $condBadgeClass }}">{{ $detailItem['condition_label'] }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Workflow Status:</span>
                            <span class="badge {{ $statusBadgeClass }}">{{ $detailItem['status_label'] }}</span>
                        </div>
                        @if(!empty($item->snapshot_data['display_name']) && $item->snapshot_data['display_name'] !== $item->name)
                            <div class="info-row">
                                <span class="info-label">Details / Brand:</span>
                                <span class="info-val">{{ $item->snapshot_data['display_name'] }}</span>
                            </div>
                        @endif
                        @if(!empty($item->snapshot_data['count']))
                            <div class="info-row">
                                <span class="info-label">Quantity / Count:</span>
                                <span class="info-val">{{ $item->snapshot_data['count'] }}</span>
                            </div>
                        @endif
                        @if(!empty($item->snapshot_data['paid_by']))
                            <div class="info-row">
                                <span class="info-label">Utility Paid By:</span>
                                <span class="info-val"><strong>{{ ucfirst($item->snapshot_data['paid_by']) }}</strong></span>
                            </div>
                        @endif
                    </td>

                    <td class="findings-col">
                        <div class="box-title">Inspector Remarks &amp; Notes</div>
                        @if($item->remarks)
                            <div class="remarks-box">
                                <strong>Fresh Inspector Remarks:</strong><br>
                                {{ $item->remarks }}
                            </div>
                        @else
                            <div style="color: #94a3b8; font-style: italic; font-size: 9px; padding: 8px 0;">
                                No fresh inspection remarks noted by the inspector.
                            </div>
                        @endif
                    </td>
                </tr>
            </table>

            <!-- Prominent Evidence Gallery Section -->
            <div class="evidence-heading">
                📷 Photographic Evidence ({{ $photosCount }} {{ $photosCount === 1 ? 'Photo' : 'Photos' }})
            </div>

            @if($photosCount === 0)
                <div class="empty-evidence-box">
                    No photo evidence was attached for this item during inspection.
                </div>
            @elseif($photosCount === 1)
                <!-- 1 Large Prominent Photo -->
                @php $p = $photos[0]; @endphp
                <div class="photo-gallery-single">
                    <div class="photo-card">
                        <img src="{{ $p['data'] }}" class="photo-img" alt="Inspection Evidence">
                        <div class="photo-meta">
                            {{ $p['file_name'] }}
                            @if($p['is_annotated'])
                                &bull; <strong style="color: #4f46e5;">🎨 Annotated Evidence</strong>
                            @endif
                        </div>

                        @if(!empty($p['annotation_layers']))
                            <div class="annotation-remarks-box">
                                <div class="annotation-remarks-title">
                                    🔍 Annotated Observations ({{ count($p['annotation_layers']) }}):
                                </div>
                                <table class="annotation-remarks-table">
                                    @foreach($p['annotation_layers'] as $layer)
                                        <tr>
                                            <td style="width: 25%;" class="annotation-label-cell">
                                                <span class="annotation-num-badge">#{{ $layer['index'] }}</span>
                                                <span class="annotation-type-tag">{{ $layer['type'] }}</span>
                                            </td>
                                            <td style="width: 75%;" class="annotation-desc-cell">
                                                {{ $layer['remark'] ?: 'Annotated marker point' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            @elseif($photosCount === 2)
                <!-- 2 Large Side-by-Side Photos -->
                <table class="photo-gallery-dual">
                    <tr>
                        @foreach($photos as $p)
                            <td>
                                <img src="{{ $p['data'] }}" class="photo-img" alt="Inspection Evidence">
                                <div class="photo-meta">
                                    {{ $p['file_name'] }}
                                    @if($p['is_annotated'])
                                        <br><strong style="color: #4f46e5;">🎨 Annotated Evidence</strong>
                                    @endif
                                </div>

                                @if(!empty($p['annotation_layers']))
                                    <div class="annotation-remarks-box">
                                        <div class="annotation-remarks-title">
                                            🔍 Annotations ({{ count($p['annotation_layers']) }}):
                                        </div>
                                        <table class="annotation-remarks-table">
                                            @foreach($p['annotation_layers'] as $layer)
                                                <tr>
                                                    <td style="width: 32%;">
                                                        <span class="annotation-num-badge">#{{ $layer['index'] }}</span>
                                                        <span class="annotation-type-tag">{{ $layer['type'] }}</span>
                                                    </td>
                                                    <td style="width: 68%; text-align: left;">
                                                        {{ $layer['remark'] ?: 'Marker point' }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                </table>
            @else
                <!-- 3+ Photos in Structured Multi-Column Grid -->
                <table class="photo-gallery-multi">
                    @foreach(array_chunk($photos, 3) as $rowPhotos)
                        <tr>
                            @foreach($rowPhotos as $p)
                                <td>
                                    <img src="{{ $p['data'] }}" class="photo-img" alt="Inspection Evidence">
                                    <div class="photo-meta">
                                        {{ $p['file_name'] }}
                                        @if($p['is_annotated'])
                                            <br><strong style="color: #4f46e5;">🎨 Annotated</strong>
                                        @endif
                                    </div>

                                    @if(!empty($p['annotation_layers']))
                                        <div class="annotation-remarks-box">
                                            <table class="annotation-remarks-table">
                                                @foreach($p['annotation_layers'] as $layer)
                                                    <tr>
                                                        <td>
                                                            <span class="annotation-num-badge">#{{ $layer['index'] }}</span>
                                                            <span class="annotation-type-tag">{{ $layer['type'] }}</span>
                                                            <span style="font-size: 8px; color: #1e293b; margin-left: 2px;">{{ $layer['remark'] ?: 'Marker' }}</span>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </table>
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                            @for($i = count($rowPhotos); $i < 3; $i++)
                                <td style="border: none; background: transparent;"></td>
                            @endfor
                        </tr>
                    @endforeach
                </table>
            @endif

            <!-- Sign-off Block on the final item page -->
            @if($loop->last)
                <div class="signoff-wrapper">
                    <div class="section-header">Sign-off &amp; Formal Approvals</div>
                    <table class="signoff-table">
                        <tr>
                            <td class="signoff-box">
                                <div class="box-title">Assigned Inspector Sign-off</div>
                                <div class="sign-line"></div>
                                <div class="info-row">
                                    <span class="info-label">Inspector Name:</span>
                                    <span class="info-val"><strong>{{ $inspector?->name ?? 'Unassigned' }}</strong></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Date Completed:</span>
                                    <span class="info-val">{{ $audit->completed_at ? $audit->completed_at->timezone(config('app.timezone', 'Asia/Kolkata'))->format('d M Y, h:i A') : ($audit->submitted_at ? $audit->submitted_at->timezone(config('app.timezone', 'Asia/Kolkata'))->format('d M Y, h:i A') : '____________________') }}</span>
                                </div>
                            </td>
                            <td class="signoff-box">
                                <div class="box-title">Reviewer &amp; Operations Approval</div>
                                <div class="sign-line"></div>
                                <div class="info-row">
                                    <span class="info-label">Reviewer Name:</span>
                                    <span class="info-val"><strong>{{ $reviewer?->name ?? 'Unassigned' }}</strong></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Date Approved:</span>
                                    <span class="info-val">{{ $audit->approved_at ? $audit->approved_at->timezone(config('app.timezone', 'Asia/Kolkata'))->format('d M Y, h:i A') : '____________________' }}</span>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            @endif
        </div>
    @endforeach
</body>
</html>
