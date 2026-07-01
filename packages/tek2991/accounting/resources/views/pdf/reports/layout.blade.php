<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $data['title'] ?? 'Report' }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', 'Helvetica', Arial, sans-serif;
            color: #333;
            font-size: 12px;
            line-height: 1.5;
            margin: 0;
            padding: 30px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .report-title {
            font-size: 24px;
            font-weight: bold;
            color: #1a202c;
            margin-bottom: 5px;
        }
        .report-subtitle {
            font-size: 14px;
            color: #718096;
            margin-bottom: 5px;
        }
        .report-period {
            font-size: 12px;
            color: #4a5568;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background-color: #f7fafc;
            color: #4a5568;
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-top: 1px solid #e2e8f0;
            border-bottom: 2px solid #e2e8f0;
        }
        .text-right {
            text-align: right !important;
        }
        .text-center {
            text-align: center !important;
        }
        .font-bold {
            font-weight: bold !important;
        }
        .group-header {
            font-weight: bold;
            background-color: #f7fafc;
        }
        .summary-row {
            font-weight: bold;
            background-color: #edf2f7;
            border-top: 2px solid #cbd5e0;
        }
        .footer {
            position: fixed;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 10px;
            color: #a0aec0;
            width: 100%;
        }
    </style>
</head>
<body>
    @if(isset($__pdf_driver) && $__pdf_driver === 'dompdf')
        <script type="text/php">
            if (isset($pdf)) {
                $text = "Page {PAGE_NUM} of {PAGE_COUNT}";
                $size = 10;
                $font = $fontMetrics->getFont("Helvetica");
                $width = $fontMetrics->get_text_width($text, $font, $size) / 2;
                $x = ($pdf->get_width() - $width) / 2;
                $y = $pdf->get_height() - 35;
                $pdf->page_text($x, $y, $text, $font, $size, array(0.6, 0.6, 0.6));
            }
        </script>
        <div class="footer">
            Generated using <strong>dompdf</strong> driver (Dev/Local Mode)
        </div>
    @endif

    <div class="header">
        <div class="report-title">{{ $data['title'] ?? 'Report' }}</div>
        @if(isset($data['subtitle']))
            <div class="report-subtitle">{{ $data['subtitle'] }}</div>
        @endif
        <div class="report-period">
            @if(isset($data['startDate']))
                Period: {{ $data['startDate'] }} to {{ $data['endDate'] ?? '' }}
            @else
                As of {{ $data['endDate'] ?? '' }}
            @endif
        </div>
    </div>

    @yield('content')

</body>
</html>
