<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - {{ $receiptNo }}</title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace, Arial, sans-serif;
            background-color: #f4f6f8;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .receipt-card {
            background: #ffffff;
            width: 320px;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            color: #000;
        }
        .receipt-header {
            text-align: center;
            margin-bottom: 12px;
        }
        .logo-wrapper {
            display: inline-block;
            background: linear-gradient(135deg, #4F2396 0%, #682eb8 100%);
            padding: 8px 16px;
            border-radius: 8px;
            margin-bottom: 6px;
        }
        .brand-logo {
            display: block;
            margin: 0 auto;
            max-width: 110px;
            max-height: 38px;
            width: auto;
            height: auto;
            object-fit: contain;
        }
        .receipt-no {
            font-size: 13px;
            color: #333;
            font-weight: 600;
            margin-top: 4px;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 12px 0;
        }
        .receipt-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
            font-size: 13px;
        }
        .receipt-label {
            font-weight: normal;
        }
        .receipt-value {
            font-weight: bold;
            text-align: right;
            max-width: 180px;
            word-wrap: break-word;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin-top: 10px;
            margin-bottom: 6px;
        }
        .amount-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            font-size: 18px;
            font-weight: bold;
        }
        .thank-you {
            text-align: center;
            margin-top: 20px;
            font-weight: bold;
            font-size: 15px;
        }
        .actions {
            margin-top: 20px;
            text-align: center;
        }
        .btn {
            background: #4F2396;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            margin: 2px;
            text-decoration: none;
        }
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .receipt-card {
                box-shadow: none;
                width: 100%;
                padding: 0;
            }
            .actions {
                display: none;
            }
        }
    </style>
</head>
<body>
    @php
        $setting = \App\Models\Admin\SystemSettingModel::first();
        $logoFile = ($setting && $setting->logo && file_exists(public_path('uploads/system_settings/' . $setting->logo))) 
            ? public_path('uploads/system_settings/' . $setting->logo) 
            : public_path('uploads/system_settings/Logo1.png');

        $logoSrc = file_exists($logoFile) 
            ? 'data:image/' . pathinfo($logoFile, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($logoFile)) 
            : asset('uploads/system_settings/Logo1.png');
    @endphp
    <div class="receipt-card">
        <div class="receipt-header">
            <div class="logo-wrapper">
                <img src="{{ $logoSrc }}" alt="System Logo" class="brand-logo">
            </div>
            <div class="receipt-no">{{ $receiptNo }}</div>
        </div>

        <div class="divider"></div>

        <div class="receipt-row">
            <span class="receipt-label">Order</span>
            <span class="receipt-value">{{ $order->id }}</span>
        </div>
        <div class="receipt-row">
            <span class="receipt-label">Date</span>
            <span class="receipt-value">{{ optional($order->created_at)->format('Y-m-d H:i:s') }}</span>
        </div>

        <div class="divider"></div>

        <div class="section-title">Customer</div>
        <div class="receipt-row">
            <span class="receipt-label">Name</span>
            <span class="receipt-value">{{ optional($order->user)->name ?? 'Customer' }}</span>
        </div>
        <div class="receipt-row">
            <span class="receipt-label">Phone</span>
            <span class="receipt-value">{{ optional($order->user)->phone ?? '-' }}</span>
        </div>

        <div class="section-title" style="margin-top: 12px;">Provider</div>
        <div class="receipt-row">
            <span class="receipt-label">Name</span>
            <span class="receipt-value">{{ optional($order->provider)->name ?? 'Provider' }}</span>
        </div>
        <div class="receipt-row">
            <span class="receipt-label">Phone</span>
            <span class="receipt-value">{{ optional($order->provider)->phone ?? '-' }}</span>
        </div>

        <div class="divider"></div>

        <div class="section-title">Job Details</div>
        <div class="receipt-row">
            <span class="receipt-label">Category</span>
            <span class="receipt-value">{{ $categoryName }}</span>
        </div>
        <div class="receipt-row">
            <span class="receipt-label">Details</span>
            <span class="receipt-value">{{ $detailsText }}</span>
        </div>

        <div class="divider"></div>

        <div class="amount-row">
            <span>Amount</span>
            <span>{{ number_format($order->price ?? 0, 0) }} SAR</span>
        </div>

        <div class="thank-you">Thank you</div>

        <div class="actions">
            <button class="btn" onclick="window.print()">🖨️ Print / Save PDF</button>
            <button class="btn" style="background: #6c757d;" onclick="window.close()">Close</button>
        </div>
    </div>
</body>
</html>
