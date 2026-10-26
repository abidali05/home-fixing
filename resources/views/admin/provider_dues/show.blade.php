@extends('layouts.app')

@section('title', 'Provider Dues Ledger Details')

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

        {{-- Provider Profile & Action Header --}}
        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar avatar-lg me-3 bg-gradient-primary rounded-circle text-white d-flex align-items-center justify-content-center fs-4">
                                {{ strtoupper(substr($provider->name ?? 'P', 0, 1)) }}
                            </div>
                            <div>
                                <h5 class="mb-0 font-weight-bold">{{ $provider->name }}</h5>
                                <p class="text-sm text-muted mb-0">Code: {{ $provider->user_code ?: 'N/A' }} | Phone: {{ $provider->phone }}</p>
                                <span class="badge bg-light text-dark border">{{ $provider->provider_type ?: 'Individual' }}</span>
                            </div>
                        </div>

                        <hr class="horizontal dark my-3">

                        <div class="row text-center">
                            <div class="col-4 border-end">
                                <p class="text-xs text-muted mb-1 text-uppercase font-weight-bold">Current Due</p>
                                <h4 class="mb-0 {{ (float)$provider->payment_due > 0 ? 'text-danger' : 'text-success' }} font-weight-bolder">
                                    SAR {{ number_format((float)($provider->payment_due ?? 0), 2) }}
                                </h4>
                            </div>
                            <div class="col-4 border-end">
                                <p class="text-xs text-muted mb-1 text-uppercase font-weight-bold">Threshold Limit</p>
                                <h4 class="mb-0 text-dark font-weight-bolder">
                                    SAR {{ number_format((float)($provider->max_due_threshold ?? 25), 2) }}
                                </h4>
                            </div>
                            <div class="col-4">
                                <p class="text-xs text-muted mb-1 text-uppercase font-weight-bold">Status</p>
                                @if ((float)$provider->payment_due >= (float)$provider->max_due_threshold && (float)$provider->payment_due > 0)
                                    <span class="badge bg-danger mt-1">Blocked</span>
                                @elseif ((float)$provider->payment_due > 0)
                                    <span class="badge bg-warning text-dark mt-1">Dues Pending</span>
                                @else
                                    <span class="badge bg-success mt-1">Clear</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Admin Actions Form --}}
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white pb-0">
                        <h6 class="mb-0 font-weight-bold">Admin Management Actions</h6>
                    </div>
                    <div class="card-body p-4 pt-2">
                        {{-- Threshold limit edit --}}
                        <form method="POST" action="{{ route('admin.provider_dues.update_threshold', $provider->id) }}" class="mb-3">
                            @csrf
                            <label class="form-label text-xs font-weight-bold text-muted mb-1">Update Max Due Threshold (SAR)</label>
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.01" min="0" name="max_due_threshold" value="{{ $provider->max_due_threshold ?? 25.0 }}" class="form-control" required>
                                <button type="submit" class="btn btn-sm btn-dark mb-0">Update Limit</button>
                            </div>
                        </form>

                        <hr class="horizontal dark my-3">

                        {{-- Manual Adjustment --}}
                        <form method="POST" action="{{ route('admin.provider_dues.adjust', $provider->id) }}">
                            @csrf
                            <label class="form-label text-xs font-weight-bold text-muted mb-1">Manual Ledger Adjustment / Office Cash Payment</label>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <select name="action" class="form-select form-select-sm" required>
                                        <option value="credit_manual_payment">Paid Cash to Office (Reduce Due)</option>
                                        <option value="clear_all">Clear All Dues (Reset to 0)</option>
                                        <option value="debit_manual_charge">Manual Charge (Increase Due)</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <input type="number" step="0.01" min="0.01" name="amount" value="{{ (float)$provider->payment_due > 0 ? (float)$provider->payment_due : 10.0 }}" class="form-control form-control-sm" placeholder="Amount (SAR)" required>
                                </div>
                            </div>
                            <div class="mb-2">
                                <input type="text" name="notes" class="form-control form-control-sm" placeholder="Notes / Receipt # (optional)">
                            </div>
                            <button type="submit" class="btn btn-sm btn-success w-100 mb-0">Apply Ledger Adjustment</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Ledger Table --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white pb-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 font-weight-bold">Audit Ledger Transactions</h6>
                <a href="{{ route('admin.provider_dues.index') }}" class="btn btn-xs btn-outline-secondary mb-0">
                    <i class="bi bi-arrow-left me-1"></i> Back to Providers
                </a>
            </div>
            <div class="card-body px-4 pt-3 pb-3">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover mb-0 align-middle text-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Date & Time</th>
                                <th>Transaction Type</th>
                                <th>Order Ref</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">Prev Due</th>
                                <th class="text-end">Current Due</th>
                                <th>Notes / Audit Log</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ledgerEntries as $entry)
                                <tr>
                                    <td>{{ $entry->created_at->format('Y-m-d H:i') }}</td>
                                    <td>
                                        @if ($entry->type === 'debit_cash_order')
                                            <span class="badge bg-danger">Debit (COD Fee Added)</span>
                                        @elseif ($entry->type === 'credit_wallet_deduction')
                                            <span class="badge bg-info text-white">Wallet Auto-Deduction</span>
                                        @elseif ($entry->type === 'credit_online_payment')
                                            <span class="badge bg-success">Online Paid via Tap</span>
                                        @else
                                            <span class="badge bg-secondary">Manual Admin Adjustment</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($entry->order_id)
                                            <span class="badge bg-light text-dark border">Order #{{ $entry->order_id }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-bold">SAR {{ number_format($entry->amount, 2) }}</td>
                                    <td class="text-end text-muted">SAR {{ number_format($entry->previous_due, 2) }}</td>
                                    <td class="text-end fw-bold {{ (float)$entry->current_due > 0 ? 'text-danger' : 'text-success' }}">
                                        SAR {{ number_format($entry->current_due, 2) }}
                                    </td>
                                    <td><small class="text-muted">{{ $entry->notes ?: '—' }}</small></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No ledger history recorded for this provider.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 d-flex justify-content-center">
                    {{ $ledgerEntries->links() }}
                </div>
            </div>
        </div>

    </div>
</main>
@endsection
