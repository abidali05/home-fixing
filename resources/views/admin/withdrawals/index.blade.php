@extends('layouts.app')

@section('title', 'Withdrawal Requests')

@section('content')
<main class="main-content position-relative border-radius-lg">
    <div class="container-fluid py-4">

        {{-- Flash Alerts --}}
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        {{-- Header Card --}}
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white pb-0 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 font-weight-bold">Provider & Marketplace Withdrawal Requests</h6>
                        <span class="badge bg-primary">Total Requests: {{ $withdrawals->count() }}</span>
                    </div>
                    <div class="card-body px-4 pt-3 pb-3">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover mb-0 align-middle text-sm" id="WithdrawalsTable">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Ref # / Date</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Type</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder">User / Owner</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Amount</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Bank Details</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Status</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($withdrawals as $withdrawal)
                                        @php
                                            $bank = $withdrawal->bankAccount;
                                            $user = $withdrawal->user;
                                            $refNo = 'WDR-' . str_pad($withdrawal->id, 6, '0', STR_PAD_LEFT);
                                            $status = strtolower($withdrawal->status);
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-dark">{{ $refNo }}</div>
                                                <small class="text-muted">{{ $withdrawal->created_at ? $withdrawal->created_at->format('Y-m-d H:i') : 'N/A' }}</small>
                                            </td>
                                            <td>
                                                @if (strtolower($withdrawal->account_type) === 'marketplace')
                                                    <span class="badge bg-gradient-info">Marketplace</span>
                                                @else
                                                    <span class="badge bg-gradient-primary">Provider</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark">{{ optional($user)->name ?? 'User #' . $withdrawal->user_id }}</div>
                                                <small class="text-muted">{{ optional($user)->phone }}</small>
                                            </td>
                                            <td>
                                                <strong class="text-dark">{{ number_format($withdrawal->amount, 2) }} {{ strtoupper($withdrawal->currency ?: 'SAR') }}</strong>
                                            </td>
                                            <td>
                                                @if ($bank)
                                                    <div class="fw-bold text-dark">{{ $bank->bank_name }}</div>
                                                    <small class="d-block text-muted">Title: {{ $bank->account_title }}</small>
                                                    <code class="text-xs">{{ $bank->iban }}</code>
                                                @else
                                                    <span class="text-muted">N/A</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if (in_array($status, ['requested', 'pending']))
                                                    <span class="badge bg-warning text-dark">REQUESTED</span>
                                                @elseif ($status === 'accepted')
                                                    <span class="badge bg-info">ACCEPTED</span>
                                                @elseif (in_array($status, ['completed', 'paid', 'approved']))
                                                    <span class="badge bg-success">COMPLETED</span>
                                                @elseif ($status === 'rejected')
                                                    <span class="badge bg-danger">REJECTED</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ strtoupper($status) }}</span>
                                                @endif

                                                @if ($withdrawal->admin_notes)
                                                    <small class="d-block text-muted mt-1" style="max-width: 180px;"><em>{{ $withdrawal->admin_notes }}</em></small>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if (!in_array($status, ['completed', 'paid', 'rejected']))
                                                    <div class="btn-group" role="group">
                                                        @if ($status !== 'accepted')
                                                            <form action="{{ route('admin.withdrawals.accept', $withdrawal->id) }}" method="POST" class="d-inline">
                                                                @csrf
                                                                @method('PATCH')
                                                                <button type="submit" class="btn btn-xs btn-outline-primary mb-0" onclick="return confirm('Accept this withdrawal request?')">
                                                                    Accept
                                                                </button>
                                                            </form>
                                                        @endif

                                                        <button type="button" class="btn btn-xs btn-outline-success mb-0" data-bs-toggle="modal" data-bs-target="#completeModal{{ $withdrawal->id }}">
                                                            Complete
                                                        </button>

                                                        <button type="button" class="btn btn-xs btn-outline-danger mb-0" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $withdrawal->id }}">
                                                            Reject
                                                        </button>
                                                    </div>

                                                    {{-- Modal: Complete Transfer --}}
                                                    <div class="modal fade text-start" id="completeModal{{ $withdrawal->id }}" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <form action="{{ route('admin.withdrawals.complete', $withdrawal->id) }}" method="POST">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title">Mark Withdrawal {{ $refNo }} as Completed</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <p class="mb-2">Enter the Bank Transfer Reference Number after transferring <strong>{{ number_format($withdrawal->amount, 2) }} SAR</strong>:</p>
                                                                        <div class="mb-3">
                                                                            <label for="bank_reference{{ $withdrawal->id }}" class="form-label font-weight-bold">Bank Reference #</label>
                                                                            <input type="text" class="form-control" name="bank_reference" id="bank_reference{{ $withdrawal->id }}" placeholder="e.g. TXN-984512784" required>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary btn-sm mb-0" data-bs-dismiss="modal">Cancel</button>
                                                                        <button type="submit" class="btn btn-success btn-sm mb-0">Confirm & Complete</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {{-- Modal: Reject Request --}}
                                                    <div class="modal fade text-start" id="rejectModal{{ $withdrawal->id }}" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <form action="{{ route('admin.withdrawals.reject', $withdrawal->id) }}" method="POST">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title">Reject Withdrawal Request {{ $refNo }}</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <p class="text-danger mb-2">Rejecting this request will release the reserved <strong>{{ number_format($withdrawal->amount, 2) }} SAR</strong> back to the provider/seller's available balance.</p>
                                                                        <div class="mb-3">
                                                                            <label for="reason{{ $withdrawal->id }}" class="form-label font-weight-bold">Rejection Reason</label>
                                                                            <textarea class="form-control" name="reason" id="reason{{ $withdrawal->id }}" rows="3" placeholder="e.g. The submitted IBAN could not be verified." required></textarea>
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
                                            <td colspan="7" class="text-center text-muted py-4">No withdrawal requests found.</td>
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
    if ($('#WithdrawalsTable').length && !$.fn.DataTable.isDataTable('#WithdrawalsTable')) {
        $('#WithdrawalsTable').DataTable({ responsive: true, order: [[0, 'desc']], pageLength: 25 });
    }
</script>
@endpush
@endsection
