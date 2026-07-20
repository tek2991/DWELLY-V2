<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>KYC Documents</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .page {
            page-break-after: always;
            padding: 20px;
        }
        .page:last-child {
            page-break-after: auto;
        }
        .header {
            border-bottom: 2px solid #555;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header-top {
            font-size: 16px;
            font-weight: bold;
            color: #333;
            text-transform: uppercase;
        }
        .header-ref {
            float: right;
            font-size: 14px;
        }
        .header-info {
            margin-top: 5px;
            font-size: 14px;
            color: #555;
        }
        .attachment-container {
            text-align: center;
        }
        .attachment-container img {
            max-width: 100%;
            max-height: 800px;
            border: 1px solid #ccc;
            padding: 5px;
        }
        .attachment-title {
            margin-bottom: 10px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    @if(isset($attachments) && count($attachments) > 0)
        @foreach($attachments as $attachment)
            <div class="page">
                <div class="header">
                    <div class="header-top">
                        DWELLY - KYC & Documents
                        <span class="header-ref">MOU Reference: {{ $mou->number ?? 'N/A' }}</span>
                    </div>
                    <div class="header-info">
                        <strong>Name:</strong> {{ $attachment['ownerName'] }} - {{ $attachment['ownerType'] }}
                    </div>
                </div>
                
                <div class="attachment-container">
                    <div class="attachment-title">{{ $attachment['name'] }}</div>
                    <img src="{{ $attachment['data'] }}" alt="Attachment">
                </div>
            </div>
        @endforeach
    @else
        <div class="page">
            <div class="header">
                <div class="header-top">
                    DWELLY - KYC & Documents
                    <span class="header-ref">MOU Reference: {{ $mou->number ?? 'N/A' }}</span>
                </div>
            </div>
            <p>No documents found.</p>
        </div>
    @endif
</body>
</html>
