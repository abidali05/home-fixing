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
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold text-muted">Azhl Earnings ({{ $stats['azhl_percentage'] }}%)</p>
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
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold text-muted">Provider Payouts</p>
                                    <h5 class="font-weight-bolder mb-0 text-info">
                                        {{ number_format($stats['provider_payouts'], 2) }} SAR
                                    </h5>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-info shadow-info text-center rounded-circle p-3">
                                    <i class="ni ni-briefcase-24 text-white text-lg opacity-10"></i>
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

        {{-- Main Payments Table Card --}}
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white pb-0 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 font-weight-bold">Payments & Transactions History</h6>
                        <span class="badge bg-light text-dark">Azhl Commission: <strong>{{ $stats['azhl_percentage'] }}%</strong></span>
                    </div>
                    <div class="card-body px-4 pt-3 pb-3">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover mb-0 align-middle text-sm" id="PaymentsTable">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder">ID / Date</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Customer</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Provider</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Job / Bid</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Amount (Gross)</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Azhl Cut ({{ $stats['azhl_percentage'] }}%)</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Tap Charge ID</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Status</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($payments as $payment)
                                        @php
                                            $isCaptured = strtolower($payment->status) === 'captured';
                                            $grossAmount = (float) $payment->amount;
                                            $azhlCut = $isCaptured ? ($grossAmount * ($stats['azhl_percentage'] / 100)) : 0;
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="fw-bold">#{{ $payment->id }}</div>
                                                <small class="text-muted">{{ $payment->created_at ? $payment->created_at->format('Y-m-d H:i') : 'N/A' }}</small>
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

                                                {{-- Modal for Details --}}
                                                <div class="modal fade" id="paymentModal{{ $payment->id }}" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog modal-lg text-start">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Payment #{{ $payment->id }} Details</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="row mb-3">
                                                                    <div class="col-md-6">
                                                                        <p class="mb-1"><strong>Payment ID:</strong> #{{ $payment->id }}</p>
                                                                        <p class="mb-1"><strong>Tap Charge ID:</strong> {{ $payment->tap_charge_id ?: 'N/A' }}</p>
                                                                        <p class="mb-1"><strong>Amount:</strong> {{ number_format($payment->amount, 2) }} {{ $payment->currency }}</p>
                                                                        <p class="mb-1"><strong>Azhl Commission ({{ $stats['azhl_percentage'] }}%):</strong> {{ number_format($azhlCut, 2) }} SAR</p>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <p class="mb-1"><strong>Customer:</strong> {{ optional($payment->user)->name }} ({{ optional($payment->user)->phone }})</p>
                                                                        <p class="mb-1"><strong>Provider:</strong> {{ optional($payment->provider)->name }} ({{ optional($payment->provider)->phone }})</p>
                                                                        <p class="mb-1"><strong>Job ID:</strong> #{{ $payment->job_id }}</p>
                                                                        <p class="mb-1"><strong>Status:</strong> <span class="badge bg-secondary">{{ strtoupper($payment->status) }}</span></p>
                                                                    </div>
                                                                </div>
                                                                <h6>Gateway Response Payload:</h6>
                                                                <pre class="bg-dark text-light p-3 rounded" style="max-height: 250px; overflow-y: auto;"><code>{{ json_encode($payment->gateway_response, JSON_PRETTY_PRINT) }}</code></pre>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary btn-sm mb-0" data-bs-dismiss="modal">Close</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">No payment transactions found.</td>
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
    $(document)
    $.fn.dataTable.ext.errMode = 'none';
    if ($('#PaymentsTable').length && !$.fn.DataTable.isDataTable('#PaymentsTable')) {
        $('#PaymentsTable').DataTable({
            responsive: true,
            order: [[0, 'desc']],
            pageLength: 25
        });
    }
</script>
@endpush
@endsection
