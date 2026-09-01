@extends('layouts.app')

@section('title', 'Customer Refund Requests')

@section('content')
<main class="main-content position-relative border-radius-lg">
    <div class="container-fluid py-4">

        {{-- Flash Alerts --}}
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show text-white" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show text-white" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        {{-- Header Card --}}
        <div class="row">
            <div class="col-12">
                {{-- Category Filter Tabs --}}
                <ul class="nav nav-pills mb-3" id="refundTabs">
                    <li class="nav-item me-2">
                        <a class="nav-link {{ empty($type) || $type === 'all' ? 'active bg-primary text-white fw-bold' : 'bg-white text-dark border' }}" href="{{ route('admin.refunds.index') }}">
                            <i class="bi bi-grid-fill me-1"></i> All Refunds ({{ $allCount }})
                        </a>
                    </li>
                    <li class="nav-item me-2">
                        <a class="nav-link {{ $type === 'service' ? 'active bg-primary text-white fw-bold' : 'bg-white text-dark border' }}" href="{{ route('admin.refunds.index', ['type' => 'service']) }}">
                            <i class="bi bi-wrench-adjustable me-1"></i> Service Order Refunds ({{ $serviceCount }})
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $type === 'marketplace' ? 'active bg-primary text-white fw-bold' : 'bg-white text-dark border' }}" href="{{ route('admin.refunds.index', ['type' => 'marketplace']) }}">
                            <i class="bi bi-cart-check me-1"></i> Marketplace Order Refunds ({{ $marketplaceCount }})
                        </a>
                    </li>
                </ul>

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white pb-0 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 font-weight-bold">
                            @if ($type === 'service')
                                Service Order Refund Requests
                            @elseif ($type === 'marketplace')
                                Marketplace Order Refund Requests
                            @else
                                Customer Order Refund Requests (All)
                            @endif
                        </h6>
                        <span class="badge bg-primary">Total Shown: {{ $refunds->count() }}</span>
                    </div>
                    <div class="card-body px-4 pt-3 pb-3">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover mb-0 align-middle text-sm" id="RefundsTable">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Ref # / Date (Saudi Time)</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Order / Cancelled By</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Customer</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Refund Amount</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Bank Details</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Status</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($refunds as $refund)
                                        @php
                                            $bank = $refund->bankAccount;
                                            $customer = $refund->customer;
                                            $order = $refund->order;
                                            $status = strtolower($refund->status);
                                            $saudiDate = $refund->created_at ? $refund->created_at->setTimezone('Asia/Riyadh')->format('Y-m-d H:i:s') : 'N/A';
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-dark">{{ $refund->refund_no }}</div>
                                                <small class="text-muted" title="Saudi Arabia Time (Asia/Riyadh)">{{ $saudiDate }}</small>
                                            </td>
                                            <td>
                                                @if ($refund->marketplace_order_id)
                                                    <span class="badge bg-purple text-white">Marketplace Order #{{ $refund->marketplace_order_id }}</span>
                                                @else
                                                    <span class="badge bg-outline-info text-info">Service Order #{{ $refund->order_id }}</span>
                                                @endif
                                                @if ($order && $order->cancelled_by_type)
                                                    <small class="d-block text-muted">By: <strong>{{ ucfirst($order->cancelled_by_type) }}</strong></small>
                                                @endif
                                                @if ($order && $order->cancellation_reason)
                                                    <small class="d-block text-muted" style="max-width: 180px; word-wrap: break-word;"><em>"{{ $order->cancellation_reason }}"</em></small>
                                                @endif
                                                @if ($refund->admin_notes)
                                                    <small class="d-block text-muted" style="max-width: 180px; word-wrap: break-word;"><em>"{{ $refund->admin_notes }}"</em></small>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark">{{ optional($customer)->name ?? 'User #' . $refund->customer_id }}</div>
                                                <small class="text-muted">{{ optional($customer)->phone }}</small>
                                            </td>
                                            <td>
                                                @php
                                                    $repairPrice = (float) ($refund->amount ?? 0);
                                                    $paidAmount = 0.0;
                                                    if ($refund->order) {
                                                        $ord = $refund->order;
                                                        $repairPrice = (float) ($ord->price ?? 0);
                                                        if (!empty($ord->job_id)) {
                                                            $acceptedBid = \App\Models\BidModel::where('job_id', $ord->job_id)->whereIn('status', ['accepted', 'completed', 'hired', 'cancelled'])->first();
                                                            if ($acceptedBid && (float) $acceptedBid->price > 0) {
                                                                $repairPrice = (float) $acceptedBid->price;
                                                            }
                                                        }
                                                        if ($repairPrice > 103) {
                                                            $settings = \App\Models\Admin\SystemSettingModel::first();
                                                            $customerAppFee = (float) ($settings->customer_app_fee ?? 3.00);
                                                            $gatewayFeePct = (float) ($settings->payment_gateway_fee_percentage ?? 2.50);
                                                            $gatewayFixedFee = (float) ($settings->payment_gateway_fixed_fee ?? 1.00);
                                                            $gatewayVatPct = (float) ($settings->payment_gateway_vat_percentage ?? 15.00);

                                                            $approxSubtotal = ($repairPrice - $gatewayFixedFee * (1 + $gatewayVatPct / 100)) / (1 + ($gatewayFeePct / 100) * (1 + $gatewayVatPct / 100));
                                                            $estimatedRepair = max(0, $approxSubtotal - $customerAppFee);
                                                            $repairPrice = abs($estimatedRepair - round($estimatedRepair)) < 0.1 ? (float) round($estimatedRepair) : (float) round($estimatedRepair, 2);
                                                        }
                                                        $pmt = \App\Models\Payment::where('job_id', $ord->job_id)->orWhere('id', $ord->id)->where('status', 'captured')->latest()->first();
                                                        $paidAmount = $pmt ? (float) $pmt->amount : ($repairPrice + 3.00 + 4.11);
                                                    } else {
                                                        $repairPrice = (float) ($refund->amount ?? 0);
                                                        $paidAmount = $repairPrice;
                                                    }
                                                    $deductedFees = max(0, $paidAmount - $repairPrice);
                                                @endphp
                                                <div class="fw-bold text-success" style="font-size: 0.95rem;">Net Refund: {{ number_format($repairPrice, 2) }} {{ strtoupper($refund->currency ?: 'SAR') }}</div>
                                                @if ($paidAmount > $repairPrice)
                                                    <small class="d-block text-muted">Paid: {{ number_format($paidAmount, 2) }} SAR</small>
                                                    <small class="d-block text-danger">Fees Retained: {{ number_format($deductedFees, 2) }} SAR</small>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($bank)
                                                    <div class="fw-bold text-dark">{{ $bank->bank_name }}</div>
                                                    <small class="d-block text-muted">Title: {{ $bank->account_title }}</small>
                                                    <code class="text-xs" style="word-break: break-all;">{{ $bank->iban }}</code>
                                                @else
                                                    <span class="text-muted">N/A</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if (in_array($status, ['requested', 'processing', 'pending']))
                                                    <span class="badge bg-warning text-dark">REQUESTED</span>
                                                @elseif ($status === 'accepted')
                                                    <span class="badge bg-info">ACCEPTED</span>
                                                @elseif (in_array($status, ['refunded', 'completed', 'paid']))
                                                    <span class="badge bg-success">REFUNDED</span>
                                                @elseif (in_array($status, ['rejected', 'failed']))
                                                    <span class="badge bg-danger">REJECTED</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ strtoupper($status) }}</span>
                                                @endif

                                                @if ($refund->admin_notes || $refund->failure_reason)
                                                    <small class="d-block text-muted mt-1" style="max-width: 200px; word-wrap: break-word; word-break: break-word;"><em>{{ $refund->admin_notes ?: $refund->failure_reason }}</em></small>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if (!in_array($status, ['refunded', 'completed', 'paid', 'rejected', 'failed']))
                                                    <div class="btn-group" role="group">
                                                        @if ($status !== 'accepted')
                                                            <form action="{{ route('admin.refunds.accept', $refund->id) }}" method="POST" class="d-inline">
                                                                @csrf
                                                                @method('PATCH')
                                                                <button type="submit" class="btn btn-xs btn-outline-primary mb-0" onclick="return confirm('Accept this refund request?')">
                                                                    Accept
                                                                </button>
                                                            </form>
                                                        @endif

                                                        <button type="button" class="btn btn-xs btn-outline-success mb-0" data-bs-toggle="modal" data-bs-target="#completeRefundModal{{ $refund->id }}">
                                                            Complete
                                                        </button>

                                                        <button type="button" class="btn btn-xs btn-outline-danger mb-0" data-bs-toggle="modal" data-bs-target="#rejectRefundModal{{ $refund->id }}">
                                                            Reject
                                                        </button>
                                                    </div>

                                                    {{-- Modal: Complete Refund Transfer --}}
                                                    <div class="modal fade text-start" id="completeRefundModal{{ $refund->id }}" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog modal-dialog-centered">
                                                            <div class="modal-content" style="max-width: 100%; overflow: hidden;">
                                                                <form action="{{ route('admin.refunds.complete', $refund->id) }}" method="POST">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title font-weight-bold">Complete Refund {{ $refund->refund_no }}</h5>
                                                                        <button type="button" class="btn-close text-dark" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body" style="word-wrap: break-word; word-break: break-word; white-space: normal;">
                                                                        <p class="mb-3 text-sm text-dark" style="word-wrap: break-word; word-break: break-word; white-space: normal;">
                                                                            Enter the Bank Transfer / Refund Reference Number after refunding <strong>{{ number_format($refund->amount, 2) }} SAR</strong> to customer:
                                                                        </p>
                                                                        <div class="mb-3">
                                                                            <label for="bank_reference{{ $refund->id }}" class="form-label font-weight-bold">Bank / Refund Reference #</label>
                                                                            <input type="text" class="form-control" name="bank_reference" id="bank_reference{{ $refund->id }}" placeholder="e.g. REF-TXN-984512" required>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary btn-sm mb-0" data-bs-dismiss="modal">Cancel</button>
                                                                        <button type="submit" class="btn btn-success btn-sm mb-0">Confirm & Complete Refund</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {{-- Modal: Reject Refund Request --}}
                                                    <div class="modal fade text-start" id="rejectRefundModal{{ $refund->id }}" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog modal-dialog-centered">
                                                            <div class="modal-content" style="max-width: 100%; overflow: hidden;">
                                                                <form action="{{ route('admin.refunds.reject', $refund->id) }}" method="POST">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title font-weight-bold">Reject Refund Request {{ $refund->refund_no }}</h5>
                                                                        <button type="button" class="btn-close text-dark" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body" style="word-wrap: break-word; word-break: break-word; white-space: normal;">
                                                                        <p class="text-danger text-sm mb-3" style="word-wrap: break-word; word-break: break-word; white-space: normal;">
                                                                            Specify the reason for rejecting this refund request of <strong>{{ number_format($refund->amount, 2) }} SAR</strong>:
                                                                        </p>
                                                                        <div class="mb-3">
                                                                            <label for="reason{{ $refund->id }}" class="form-label font-weight-bold">Rejection Reason</label>
                                                                            <textarea class="form-control" name="reason" id="reason{{ $refund->id }}" rows="3" placeholder="e.g. Customer bank account details could not be verified." required></textarea>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary btn-sm mb-0" data-bs-dismiss="modal">Cancel</button>
                                                                        <button type="submit" class="btn btn-danger btn-sm mb-0">Confirm Rejection</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @else
                                                    <span class="text-muted text-xs">No pending actions</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">No customer refund requests found.</td>
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
</main>

@push('scripts')
<script>
    $.fn.dataTable.ext.errMode = 'none';
    if ($('#RefundsTable').length && !$.fn.DataTable.isDataTable('#RefundsTable')) {
        $('#RefundsTable').DataTable({ responsive: true, order: [[0, 'desc']], pageLength: 25 });
    }
</script>
@endpush
@endsection
