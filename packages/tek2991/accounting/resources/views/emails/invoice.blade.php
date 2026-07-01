<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2>Hello {{ $invoice->contact->name }},</h2>
        
        <p>Please find attached your invoice <strong>{{ $invoice->invoice_number }}</strong>.</p>
        
        <div style="background-color: #f9fafb; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <p style="margin: 0;"><strong>Amount Due:</strong> {{ (new \Tek2991\Accounting\ValueObjects\Money($invoice->getRawOriginal('balance_due'), $invoice->currency_code))->format() }}</p>
            <p style="margin: 5px 0 0 0;"><strong>Due Date:</strong> {{ $invoice->due_date ? $invoice->due_date->format('M d, Y') : 'Due on receipt' }}</p>
        </div>
        
        <p>If you have any questions, please don't hesitate to contact us.</p>
        
        <p>Best regards,</p>
        <p>{{ config('app.name') }}</p>
    </div>
</body>
</html>
