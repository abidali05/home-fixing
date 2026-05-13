@extends('layouts.app')

@section('title', 'Marketplace Order Details')

@push('styles')
    <style>
        .receipt-shell {
            background: linear-gradient(180deg, #f7fafc 0%, #eef5f8 100%);
            border: 1px solid #e4ebf1;
            border-radius: 24px;
            padding: 1.5rem;
        }

        .receipt-card {
            background: #fff;
            border: 1px solid #e6edf3;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(31, 53, 72, 0.08);
        }

        .receipt-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.5rem 1.75rem;
            background: linear-gradient(135deg, #173042 0%, #24566d 100%);
            color: #fff;
        }

        .receipt-brand-title {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            color: #ffffff;
        }

        .receipt-brand-subtitle {
            margin: 0.4rem 0 0;
            color: rgba(255, 255, 255, 0.72);
            font-size: 0.9rem;
        }

        .receipt-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.45rem 0.8rem;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            background: rgba(255, 255, 255, 0.16);
            color: #fff;
        }

        .receipt-body {
            padding: 1.75rem;
        }

        .receipt-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .receipt-block {
            border: 1px solid #e6edf3;
            border-radius: 18px;
            padding: 1rem 1.1rem;
            background: #fbfdff;
        }

        .receipt-block-title {
            margin: 0 0 0.7rem;
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #6e8190;
        }

        .receipt-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.8rem 1rem;
            margin-bottom: 1.5rem;
        }

        .receipt-meta-item {
            border-bottom: 1px dashed #e0e7ee;
            padding-bottom: 0.55rem;
        }

        .receipt-meta-label {
            display: block;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #7a8c9a;
            margin-bottom: 0.25rem;
            font-weight: 700;
        }

        .receipt-meta-value {
            color: #223645;
            font-weight: 600;
        }

        .receipt-table-wrap {
            border: 1px solid #e6edf3;
            border-radius: 18px;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .receipt-table {
            width: 100%;
            margin: 0;
        }

        .receipt-table thead th {
            background: #f5f9fc;
            color: #5f7281;
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 0.9rem 1rem;
            border-bottom: 1px solid #e6edf3;
        }

        .receipt-table tbody td {
            padding: 0.95rem 1rem;
            color: #304350;
            border-bottom: 1px solid #eef3f6;
            vertical-align: top;
        }

        .receipt-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .receipt-summary {
            margin-left: auto;
            width: min(100%, 360px);
            border: 1px solid #e6edf3;
            border-radius: 18px;
            padding: 1rem 1.1rem;
            background: #fcfdff;
        }

        .receipt-summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 0.55rem 0;
            border-bottom: 1px dashed #e2eaf0;
            color: #486070;
        }

        .receipt-summary-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .receipt-summary-row.total {
            margin-top: 0.4rem;
            padding-top: 0.9rem;
            border-top: 1px solid #d9e5ec;
            font-size: 1rem;
            font-weight: 800;
            color: #183244;
        }

        .receipt-notes {
            margin-top: 1.5rem;
        }

        .receipt-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .receipt-status-pair {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .receipt-update-card {
            border: 1px solid #e6edf3;
            border-radius: 24px;
            box-shadow: 0 16px 36px rgba(31, 53, 72, 0.08);
        }

        .receipt-update-card .card-header {
            background: #fff;
            border-bottom: 1px solid #edf2f7;
            padding: 1.1rem 1.25rem;
        }

        .receipt-update-card .card-body {
            padding: 1.25rem;
        }

        @media (max-width: 991.98px) {
            .receipt-grid,
            .receipt-meta {
                grid-template-columns: 1fr;
            }

            .receipt-actions,
            .receipt-header {
                flex-direction: column;
                align-items: stretch;
            }

            .receipt-summary {
                width: 100%;
            }
        }

        @media print {
            body {
                background: #fff !important;
            }

            #sidenav-main,
            .fixed-plugin,
            .admin-no-print,
            .navbar,
            .receipt-update-col {
                display: none !important;
            }

            .main-content {
                margin: 0 !important;
            }

            .container-fluid,
            .receipt-shell,
            .receipt-card {
                padding: 0 !important;
                border: 0 !important;
                box-shadow: none !important;
                background: #fff !important;
            }

            .card,
            .row,
            .col-lg-8 {
                width: 100% !important;
                max-width: 100% !important;
                flex: 0 0 100% !important;
            }
        }
    </style>
@endpush

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="receipt-shell">
                        <div class="receipt-actions admin-no-print">
                            <a href="{{ route('marketplace.orders.index') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-arrow-left me-1"></i> Back to Orders
                            </a>
                            <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                                <i class="bi bi-printer me-1"></i> Print Receipt
                            </button>
                        </div>

                        <div class="receipt-card">
                            <div class="receipt-header">
                                <div>
                                    <h1 class="receipt-brand-title">Marketplace Receipt</h1>
                                    <p class="receipt-brand-subtitle">Home Fixing order summary and payment breakdown</p>
                                </div>
                                <div class="text-lg-end">
                                    <div class="receipt-badge">Order #{{ $order->order_number }}</div>
                                    <div class="mt-3 receipt-status-pair">
                                        <span class="receipt-badge">{{ ucwords(str_replace('_', ' ', $order->status)) }}</span>
                                        <span class="receipt-badge">{{ ucfirst($order->payment_status ?? 'pending') }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="receipt-body">
                                <div class="receipt-grid">
                                    <div class="receipt-block">
                                        <h6 class="receipt-block-title">Billed To</h6>
                                        <div class="fw-semibold text-dark">{{ $order->customer?->name ?: 'Walk-in Customer' }}</div>
                                        <div class="text-muted">{{ $order->customer?->email ?: 'No email available' }}</div>
                                        <div class="text-muted">{{ $order->customer?->phone ?: 'No phone available' }}</div>
                                    </div>
                                    <div class="receipt-block">
                                        <h6 class="receipt-block-title">Shipping Address</h6>
                                        <div class="fw-semibold text-dark">{{ $order->shipping_address ?: 'No shipping address provided' }}</div>
                                        <div class="text-muted mt-2">Payment Method: {{ $order->payment_method ?: 'Not specified' }}</div>
                                    </div>
                                </div>

                                <div class="receipt-meta">
                                    <div class="receipt-meta-item">
                                        <span class="receipt-meta-label">Order Date</span>
                                        <span class="receipt-meta-value">{{ optional($order->created_at)->format('d M Y, h:i A') ?: '-' }}</span>
                                    </div>
                                    <div class="receipt-meta-item">
                                        <span class="receipt-meta-label">Customer ID</span>
                                        <span class="receipt-meta-value">#{{ $order->customer?->user_code ?: '-' }}</span>
                                    </div>
                                    <div class="receipt-meta-item">
                                        <span class="receipt-meta-label">Coupon Code</span>
                                        <span class="receipt-meta-value">{{ $order->coupon_code ?: 'Not applied' }}</span>
                                    </div>
                                    <div class="receipt-meta-item">
                                        <span class="receipt-meta-label">Total Items</span>
                                        <span class="receipt-meta-value">{{ $order->items->sum('quantity') }}</span>
                                    </div>
                                </div>

                                <div class="receipt-table-wrap">
                                    <table class="receipt-table">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Seller</th>
                                                <th class="text-center">Qty</th>
                                                <th class="text-end">Unit Price</th>
                                                <th class="text-end">Line Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($order->items as $item)
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold">{{ $item->product_name }}</div>
                                                        <div class="text-muted small">Product ID: #{{ $item->product_id ?: '-' }}</div>
                                                    </td>
                                                    <td>
                                                        {{ $item->shop?->marketplaceProfile?->shop_title ?: $item->shop?->name ?: '-' }}
                                                    </td>
                                                    <td class="text-center">{{ $item->quantity }}</td>
                                                    <td class="text-end">SAR {{ number_format($item->base_price, 2) }}</td>
                                                    <td class="text-end fw-semibold">SAR {{ number_format($item->total_price, 2) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="receipt-summary">
                                    <div class="receipt-summary-row">
                                        <span>Subtotal</span>
                                        <strong>SAR {{ number_format($order->subtotal, 2) }}</strong>
                                    </div>
                                    <div class="receipt-summary-row">
                                        <span>Shipping</span>
                                        <strong>SAR {{ number_format($order->shipping_cost ?? 0, 2) }}</strong>
                                    </div>
                                    <div class="receipt-summary-row">
                                        <span>Tax</span>
                                        <strong>SAR {{ number_format($order->tax_amount ?? 0, 2) }}</strong>
                                    </div>
                                    <div class="receipt-summary-row">
                                        <span>Discount</span>
                                        <strong>SAR {{ number_format($order->discount_price ?? 0, 2) }}</strong>
                                    </div>
                                    <div class="receipt-summary-row total">
                                        <span>Total Amount</span>
                                        <strong>SAR {{ number_format($order->total_amount, 2) }}</strong>
                                    </div>
                                </div>

                                @if ($order->notes || $order->delivery_response_reason)
                                    <div class="receipt-grid receipt-notes">
                                        <div class="receipt-block">
                                            <h6 class="receipt-block-title">Order Notes</h6>
                                            <div class="text-dark">{{ $order->notes ?: 'No notes added for this order.' }}</div>
                                        </div>
                                        <div class="receipt-block">
                                            <h6 class="receipt-block-title">Delivery Response</h6>
                                            <div class="text-dark">{{ $order->delivery_response_reason ?: 'No delivery response recorded.' }}</div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 receipt-update-col">
                    <div class="card receipt-update-card">
                        <div class="card-header">
                            <h6 class="mb-0">Update Order</h6>
                        </div>
                        <div class="card-body">
                            @if (session('success'))
                                <div class="alert alert-success">{{ session('success') }}</div>
                            @endif
                            <form action="{{ route('marketplace.orders.update', $order->id) }}" method="POST" class="admin-loader-form">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">Order Status</label>
                                    <select name="status" class="form-select">
                                        @foreach ($orderStatuses as $status)
                                            <option value="{{ $status }}" {{ $order->status === $status ? 'selected' : '' }}>
                                                {{ ucwords(str_replace('_', ' ', $status)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Payment Status</label>
                                    <select name="payment_status" class="form-select">
                                        @foreach ($paymentStatuses as $paymentStatus)
                                            <option value="{{ $paymentStatus }}" {{ ($order->payment_status ?? 'pending') === $paymentStatus ? 'selected' : '' }}>
                                                {{ ucfirst($paymentStatus) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Notes</label>
                                    <textarea name="notes" rows="3" class="form-control">{{ old('notes', $order->notes) }}</textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Delivery Response Reason</label>
                                    <textarea name="delivery_response_reason" rows="3" class="form-control">{{ old('delivery_response_reason', $order->delivery_response_reason) }}</textarea>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Save Changes</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
