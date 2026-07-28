<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice - {{ $invoice->invoice_no }}</title>
    <style>
        @page {
            margin: 0;
        }
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 13px;
            color: #333333;
            line-height: 1.5;
            background: #ffffff;
        }
        .header-container {
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            line-height: 0;
            font-size: 0;
        }
        .header-banner {
            width: 101% !important;
            margin-left: -0.5% !important;
            height: auto !important;
            display: block !important;
            padding: 0 !important;
            border: 0 !important;
        }
        .footer-container {
            width: 100% !important;
            position: fixed !important;
            bottom: 0 !important;
            left: 0 !important;
            right: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            line-height: 0 !important;
            font-size: 0 !important;
            z-index: -1000 !important;
        }
        .footer-banner {
            width: 101% !important;
            margin-left: -0.5% !important;
            height: auto !important;
            display: block !important;
            padding: 0 !important;
            border: 0 !important;
        }
        .container {
            padding: 25px 45px 100px 45px;
        }
        .title {
            font-size: 22px;
            font-weight: bold;
            color: #000000;
            text-align: center;
            margin-top: 5px;
            margin-bottom: 25px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        .meta-section {
            margin-bottom: 25px;
            line-height: 1.6;
        }
        .meta-row {
            margin-bottom: 3px;
        }
        .meta-label {
            font-weight: bold;
            color: #000000;
            display: inline-block;
            width: 80px;
        }
        .meta-value {
            color: #333333;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 25px;
        }
        .items-table th {
            border: 1px solid #7f8c8d;
            padding: 7px 10px;
            text-align: left;
            font-weight: bold;
            color: #000000;
            font-size: 13px;
            background-color: transparent;
        }
        .items-table td {
            border: 1px solid #7f8c8d;
            padding: 7px 10px;
            color: #333333;
            font-size: 13px;
        }
        .bank-details {
            margin-top: 25px;
            color: #7f8c8d;
            font-size: 12.5px;
            line-height: 1.6;
            font-style: italic;
        }
        .bank-details p {
            margin: 0 0 5px 0;
        }
        .signature-block {
            margin-top: 30px;
            text-align: left;
            line-height: 1.5;
        }
        .signature-img {
            max-width: 150px;
            max-height: 50px;
            display: block;
            margin-bottom: 3px;
        }
        .signature-line {
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        .signee-name {
            font-weight: bold;
            color: #000000;
        }
        .signee-title {
            color: #555555;
        }
    </style>
</head>
<body>

    @if(!empty($snapshot) && !empty($snapshot['background_image_url']))
        <div style="position: fixed; top: 250px; left: 10%; width: 80%; opacity: 0.08; z-index: -1000; text-align: center;">
            <img src="{{ public_path($snapshot['background_image_url']) }}" style="width: 100%; max-width: 100%; height: auto;">
        </div>
    @endif

    @if(!empty($snapshot) && !empty($snapshot['header_image_url']))
        <div class="header-container">
            <img src="{{ public_path($snapshot['header_image_url']) }}" class="header-banner" alt="Header">
        </div>
    @endif

    <div class="container">
        <div class="title">INVOICE</div>

        <div class="meta-section">
            @if($project)
                <div class="meta-row"><span class="meta-label">Project:</span> <span class="meta-value">{{ $project->name }}</span></div>
            @endif
            <div class="meta-row"><span class="meta-label">Client:</span> <span class="meta-value">{{ $client->name ?? 'Unknown Client' }}</span></div>
            <div class="meta-row">
                <span class="meta-label">Date:</span> 
                <span class="meta-value">{{ \Carbon\Carbon::parse($invoice->issue_date ?? $invoice->created_at)->format('jS F Y') }}</span>
            </div>
            <div class="meta-row"><span class="meta-label">Invoice #:</span> <span class="meta-value">{{ $invoice->invoice_no }}</span></div>
        </div>

        @php
            $hasQty = false;
            foreach($items as $item) {
                if ($item->qty > 1) {
                    $hasQty = true;
                    break;
                }
            }
        @endphp

        <table class="items-table">
            <thead>
                <tr>
                    @if($hasQty)
                        <th style="width: 55%;">Description</th>
                        <th style="width: 10%; text-align: center;">Qty</th>
                        <th style="width: 15%; text-align: right;">Unit Price</th>
                        <th style="width: 20%; text-align: right;">Amount in {{ $invoice->currency }}</th>
                    @else
                        <th style="width: 70%;">Description</th>
                        <th style="width: 30%; text-align: right;">Amount in {{ $invoice->currency }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    @if($hasQty)
                        <td style="text-align: center;">{{ $item->qty }}</td>
                        <td style="text-align: right;">{{ number_format($item->unit_price, 2) }}</td>
                    @endif
                    <td style="text-align: right;">{{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
                
                <tr>
                    <td @if($hasQty) colspan="3" @endif style="font-weight: bold;">Subtotal</td>
                    <td style="text-align: right; font-weight: bold;">{{ number_format(($invoice->subtotal > 0 ? $invoice->subtotal : $items->sum('total')), 2) }}</td>
                </tr>
                
                @if(!empty($invoice->tax_amount) && $invoice->tax_amount > 0 || !empty($invoice->tax_rate) && $invoice->tax_rate > 0)
                <tr>
                    <td @if($hasQty) colspan="3" @endif style="font-weight: bold;">VAT / Tax ({{ $taxType->name ?? ($invoice->tax_rate . '%') }})</td>
                    <td style="text-align: right; font-weight: bold;">{{ number_format($invoice->tax_amount, 2) }}</td>
                </tr>
                @endif
                
                @if(!empty($invoice->advance_paid) && $invoice->advance_paid > 0)
                <tr>
                    <td @if($hasQty) colspan="3" @endif style="font-weight: bold;">Advance Paid</td>
                    <td style="text-align: right; font-weight: bold;">({{ number_format($invoice->advance_paid, 2) }})</td>
                </tr>
                @endif

                <tr>
                    <td @if($hasQty) colspan="3" @endif style="font-weight: bold; font-size: 14px; background-color: #f8fafc;">Grand Total</td>
                    <td style="text-align: right; font-weight: bold; font-size: 14px; background-color: #f8fafc;">{{ number_format(($invoice->grand_total > 0 ? $invoice->grand_total : $invoice->amount), 2) }}</td>
                </tr>
            </tbody>
        </table>


        @if(!empty($snapshot))
            @if(!empty($snapshot['description']) || !empty($snapshot['bank_details']))
            <div class="bank-details">
                @if(!empty($snapshot['description']))
                    <div style="margin-bottom: 10px;">{!! $snapshot['description'] !!}</div>
                @endif
                @if(!empty($snapshot['bank_details']))
                    {!! nl2br(e($snapshot['bank_details'])) !!}
                @endif
            </div>
            @endif
        @endif

        <div class="signature-block">
            @if(!empty($invoice->signature_image))
                <img src="{{ public_path($invoice->signature_image) }}" class="signature-img" alt="Signature">
            @endif
            <div class="signature-line">------------------------------------</div>
            <span class="signee-name">{{ $invoice->signee_name ?? 'Mrs.M.Tharsika' }}</span>,<br>
            <span class="signee-title">{{ $invoice->signee_title ?? 'Finance Manager | Apptimus.' }}</span>
        </div>
    </div>

    @if(!empty($snapshot) && !empty($snapshot['footer_image_url']))
        <div class="footer-container">
            <img src="{{ public_path($snapshot['footer_image_url']) }}" class="footer-banner" alt="Footer">
        </div>
    @endif

</body>
</html>
