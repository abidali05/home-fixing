@extends('layouts.app')

@section('title', 'Payments & Transactions')

@section('content')
<main class="main-content position-relative border-radius-lg">
    <div class="container-fluid py-4">

        {{-- Summary Cards --}}
        <div class="row mb-4">
            <div class="col-xl-3 col-sm-6 mb-xl-0 mb-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-8">
                                <div class="numbers">
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold text-muted">Total Volume</p>
                                    <h5 class="font-weight-bolder mb-0 text-dark">
                                        {{ number_format($stats['total_volume'], 2) }} SAR
                                    </h5>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-primary shadow-primary text-center rounded-circle p-3">
                                    <i class="ni ni-credit-card text-white text-lg opacity-10"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6 mb-xl-0 mb-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-8">
                                <div class="numbers">
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold text-muted">Azhl Earnings ({{ number_format($stats['azhl_fee'], 2) }} SAR/Order)</p>
                                    <h5 class="font-weight-bolder mb-0 text-success">
                                        {{ number_format($stats['system_earnings'], 2) }} SAR
                                    </h5>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-success shadow-success text-center rounded-circle p-3">
                                    <i class="ni ni-money-coins text-white text-lg opacity-10"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6 mb-xl-0 mb-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-8">
                                <div class="numbers">
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold text-muted">Marketplace Sales</p>
                                    <h5 class="font-weight-bolder mb-0 text-info">
                                        {{ number_format($stats['marketplace_volume'], 2) }} SAR
                                    </h5>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-info shadow-info text-center rounded-circle p-3">
                                    <i class="ni ni-shop text-white text-lg opacity-10"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-8">
                                <div class="numbers">
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold text-muted">Successful Payments</p>
                                    <h5 class="font-weight-bolder mb-0 text-dark">
                                        {{ number_format($stats['captured_count']) }} / {{ number_format($stats['total_transactions']) }}
                                    </h5>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-warning shadow-warning text-center rounded-circle p-3">
                                    <i class="ni ni-check-bold text-white text-lg opacity-10"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Payments Table Card with Nav Tabs --}}
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white pb-0 d-flex justify-content-between align-items-center">
                        <ul class="nav nav-tabs card-header-tabs" id="paymentTabs" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active font-weight-bold" id="services-tab" data-bs-toggle="tab" data-bs-target="#services-payments" type="button" role="tab">
                                    <i class="bi bi-tools me-1"></i> Provider Services Payments ({{ $servicePayments->count() }})
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link font-weight-bold" id="marketplace-tab" data-bs-toggle="tab" data-bs-target="#marketplace-payments" type="button" role="tab">
                                    <i class="bi bi-cart-check me-1"></i> Marketplace Product Payments ({{ $marketplacePayments->count() }})
                                </button>
                            </li>
                        </ul>
                        <span class="badge bg-light text-dark">Azhl Fixed Fee: <strong>{{ number_format($stats['azhl_fee'], 2) }} SAR / order</strong></span>
                    </div>

                    <div class="card-body px-4 pt-3 pb-3">
                        <div class="tab-content" id="paymentTabsContent">

                            {{-- Services Payments Tab --}}
                            <div class="tab-pane fade show active" id="services-payments" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover mb-0 align-middle text-sm" id="ServicesPaymentsTable">
                                        <thead class="thead-light">
                                            <tr>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder">ID / Date</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Customer</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Provider</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Job / Bid</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Amount (Gross)</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Azhl Fee ({{ number_format($stats['azhl_fee'], 2) }} SAR)</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Tap Charge ID</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Status</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder text-center">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($servicePayments as $payment)
                                                @php
                                                    $isCaptured = strtolower($payment->status) === 'captured';
                                                    $grossAmount = (float) $payment->amount;
                                                    $azhlCut = $isCaptured ? min($grossAmount, $stats['azhl_fee']) : 0;
                                                @endphp
                                                <tr>
                                                    <td>
                                                        <div class="fw-bold">#{{ $payment->id }}</div>
                                                        <small class="text-muted">{{ $payment->created_at ? $payment->created_at->setTimezone('Asia/Riyadh')->format('Y-m-d H:i') : 'N/A' }}</small>
                                                    </td>
                                                    <td>
                                                        <div class="fw-semibold text-dark">{{ optional($payment->user)->name ?? 'User #' . $payment->user_id }}</div>
                                                        <small class="text-muted">{{ optional($payment->user)->phone }}</small>
                                                    </td>
                                                    <td>
                                                        <div class="fw-semibold text-dark">{{ optional($payment->provider)->name ?? 'Provider #' . $payment->provider_id }}</div>
                                                        <small class="text-muted">{{ optional($payment->provider)->phone }}</small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-outline-primary text-primary">Job #{{ $payment->job_id }}</span>
                                                        <span class="badge bg-outline-secondary text-secondary">Bid #{{ $payment->bid_id }}</span>
                                                    </td>
                                                    <td>
                                                        <strong class="text-dark">{{ number_format($grossAmount, 2) }} {{ strtoupper($payment->currency ?: 'SAR') }}</strong>
                                                    </td>
                                                    <td>
                                                        <span class="text-success fw-bold">+{{ number_format($azhlCut, 2) }} SAR</span>
                                                    </td>
                                                    <td>
                                                        <code>{{ $payment->tap_charge_id ?: 'N/A' }}</code>
                                                    </td>
                                                    <td>
                                                        @if (strtolower($payment->status) === 'captured')
                                                            <span class="badge bg-success">CAPTURED</span>
                                                        @elseif (in_array(strtolower($payment->status), ['processing', 'pending', 'initiated']))
                                                            <span class="badge bg-warning text-dark">{{ strtoupper($payment->status) }}</span>
                                                        @else
                                                            <span class="badge bg-danger">{{ strtoupper($payment->status ?: 'FAILED') }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-xs btn-outline-info mb-0" data-bs-toggle="modal" data-bs-target="#paymentModal{{ $payment->id }}">
                                                            <i class="bi bi-eye"></i> Details
                                                        </button>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="9" class="text-center text-muted py-4">No provider service payments found.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- Marketplace Payments Tab --}}
                            <div class="tab-pane fade" id="marketplace-payments" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover mb-0 align-middle text-sm" id="MarketplacePaymentsTable">
                                        <thead class="thead-light">
                                            <tr>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder">ID / Date</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Customer</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Marketplace Order</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Amount (Gross)</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Azhl Fee ({{ number_format($stats['azhl_fee'], 2) }} SAR)</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Tap Charge ID</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Status</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder text-center">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($marketplacePayments as $payment)
                                                @php
                                                    $isCaptured = strtolower($payment->status) === 'captured';
                                                    $grossAmount = (float) $payment->amount;
                                                    $azhlCut = $isCaptured ? min($grossAmount, $stats['azhl_fee']) : 0;
                                                    $mkOrder = $payment->marketplaceOrder;
                                                @endphp
                                                <tr>
                                                    <td>
                                                        <div class="fw-bold">#{{ $payment->id }}</div>
                                                        <small class="text-muted">{{ $payment->created_at ? $payment->created_at->setTimezone('Asia/Riyadh')->format('Y-m-d H:i') : 'N/A' }}</small>
                                                    </td>
                                                    <td>
                                                        <div class="fw-semibold text-dark">{{ optional($payment->user)->name ?? 'User #' . $payment->user_id }}</div>
                                                        <small class="text-muted">{{ optional($payment->user)->phone }}</small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-outline-info text-info">Order #{{ $payment->marketplace_order_id }}</span>
                                                        @if ($mkOrder && $mkOrder->order_number)
                                                            <small class="d-block text-muted">{{ $mkOrder->order_number }}</small>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <strong class="text-dark">{{ number_format($grossAmount, 2) }} {{ strtoupper($payment->currency ?: 'SAR') }}</strong>
                                                    </td>
                                                    <td>
                                                        <span class="text-success fw-bold">+{{ number_format($azhlCut, 2) }} SAR</span>
                                                    </td>
                                                    <td>
                                                        <code>{{ $payment->tap_charge_id ?: 'N/A' }}</code>
                                                    </td>
                                                    <td>
                                                        @if (strtolower($payment->status) === 'captured')
                                                            <span class="badge bg-success">CAPTURED</span>
                                                        @elseif (in_array(strtolower($payment->status), ['processing', 'pending', 'initiated']))
                                                            <span class="badge bg-warning text-dark">{{ strtoupper($payment->status) }}</span>
                                                        @else
                                                            <span class="badge bg-danger">{{ strtoupper($payment->status ?: 'FAILED') }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-xs btn-outline-info mb-0" data-bs-toggle="modal" data-bs-target="#paymentModal{{ $payment->id }}">
                                                            <i class="bi bi-eye"></i> Details
                                                        </button>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="8" class="text-center text-muted py-4">No marketplace product payments found.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Detail Modals --}}
        @foreach ($allPayments as $payment)
            @php
                $isCaptured = strtolower($payment->status) === 'captured';
                $grossAmount = (float) $payment->amount;
                $azhlCut = $isCaptured ? min($grossAmount, $stats['azhl_fee']) : 0;
                $providerCut = max(0, $grossAmount - $azhlCut);
            @endphp
            <div class="modal fade text-start" id="paymentModal{{ $payment->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content" style="max-width: 100%; overflow: hidden;">
                        <div class="modal-header">
                            <h5 class="modal-title font-weight-bold">Payment Transaction #{{ $payment->id }} Details</h5>
                            <button type="button" class="btn-close text-dark" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" style="word-wrap: break-word; word-break: break-word; white-space: normal;">
                            <div class="row g-3">
                                <div class="col-6">
                                    <small class="text-muted d-block">Transaction Status</small>
                                    @if (strtolower($payment->status) === 'captured')
                                        <span class="badge bg-success fs-6">CAPTURED</span>
                                    @elseif (in_array(strtolower($payment->status), ['processing', 'pending', 'initiated']))
                                        <span class="badge bg-warning text-dark fs-6">{{ strtoupper($payment->status) }}</span>
                                    @else
                                        <span class="badge bg-danger fs-6">{{ strtoupper($payment->status ?: 'FAILED') }}</span>
                                    @endif
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Gross Amount</small>
                                    <strong class="fs-6 text-dark">{{ number_format($grossAmount, 2) }} {{ strtoupper($payment->currency ?: 'SAR') }}</strong>
                                </div>
                                <hr class="my-2">
                                <div class="col-6">
                                    <small class="text-muted d-block">Azhl Fixed Fee (SAR)</small>
                                    <strong class="text-success">+{{ number_format($azhlCut, 2) }} SAR</strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Provider / Seller Net Credit</small>
                                    <strong class="text-primary">{{ number_format($providerCut, 2) }} SAR</strong>
                                </div>
                                <hr class="my-2">
                                <div class="col-6">
                                    <small class="text-muted d-block">Tap Payment ID</small>
                                    <code style="word-break: break-all;">{{ $payment->payment_id ?: 'N/A' }}</code>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Tap Charge ID</small>
                                    <code style="word-break: break-all;">{{ $payment->tap_charge_id ?: 'N/A' }}</code>
                                </div>
                                <div class="col-12">
                                    <small class="text-muted d-block">Payment Date & Time (Saudi Time)</small>
                                    <strong>{{ $payment->created_at ? $payment->created_at->setTimezone('Asia/Riyadh')->format('Y-m-d H:i:s') : 'N/A' }}</strong>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm mb-0" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

    </div>
</main>

@push('scripts')
<script>
    $.fn.dataTable.ext.errMode = 'none';
    if ($('#ServicesPaymentsTable').length && !$.fn.DataTable.isDataTable('#ServicesPaymentsTable')) {
        $('#ServicesPaymentsTable').DataTable({ responsive: true, order: [[0, 'desc']], pageLength: 25 });
    }
    if ($('#MarketplacePaymentsTable').length && !$.fn.DataTable.isDataTable('#MarketplacePaymentsTable')) {
        $('#MarketplacePaymentsTable').DataTable({ responsive: true, order: [[0, 'desc']], pageLength: 25 });
    }
</script>
@endpush
@endsection
