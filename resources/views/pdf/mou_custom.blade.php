<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>MOU Document - {{ $mou->number ?? 'Agreement' }}</title>
    <style>
        @page {
            margin: 40px 45px 50px 45px;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 13.5px;
            line-height: 1.5;
            color: #222;
        }
        h1, h2, h3, h4 {
            color: #000;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
        }
        .header h1 {
            font-size: 30px;
            margin-bottom: 8px;
            color: #333;
        }
        .header h3 {
            font-size: 17px;
            text-decoration: underline;
        }
        .section-title {
            font-weight: bold;
            text-decoration: underline;
            margin-top: 18px;
            margin-bottom: 8px;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 11px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .signature-section {
            margin-top: 50px;
            width: 100%;
        }
        .signature-box {
            display: inline-block;
            width: 45%;
        }
        .signature-line {
            border-bottom: 1px solid #000;
            width: 100%;
            margin-bottom: 5px;
            height: 35px;
        }
        .form-line {
            display: inline-block;
            border-bottom: 1px solid #000;
            min-width: 120px;
            padding: 0 4px;
        }
        ul, ol {
            margin-top: 5px;
            margin-bottom: 12px;
            padding-left: 20px;
        }
        li {
            margin-bottom: 4px;
        }
        .page-break {
            page-break-after: always;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        td, th {
            padding: 6px;
        }
    </style>
</head>
<body>

    {!! $content !!}

    <div class="footer">
        Dwelly (Assam Alay), Registered Office: #61, Basistha Road, Beltola, Guwahati, Assam – 781028,<br>
        M: +91-80994 94817, Email: assamalay@gmail.com
    </div>

</body>
</html>
