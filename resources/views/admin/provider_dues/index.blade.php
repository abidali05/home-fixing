@extends('layouts.app')

@section('title', 'Provider Platform Dues & Commissions')

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

        {{-- Statistics Row --}}
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-sm-6">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center">
                            <div class="icon icon-shape bg-gradient-danger shadow text-center border-radius-md me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                <i class="ni ni-money-coins text-lg text-white"></i>
                            </div>
                            <div>
                                <p class="text-xs mb-0 text-uppercase font-weight-bold text-muted">Total Unpaid Dues</p>
                                <h5 class="font-weight-bolder mb-0 text-danger">SAR {{ number_format($totalDuesAmount, 2) }}</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center">
                            <div class="icon icon-shape bg-gradient-warning shadow text-center border-radius-md me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                <i class="bi bi-exclamation-triangle-fill text-lg text-white"></i>
                            </div>
                            <div>
                                <p class="text-xs mb-0 text-uppercase font-weight-bold text-muted">Blocked Providers</p>
                                <h5 class="font-weight-bolder mb-0 text-warning">{{ $blockedProviders }}</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center">
                            <div class="icon icon-shape bg-gradient-info shadow text-center border-radius-md me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                <i class="bi bi-people-fill text-lg text-white"></i>
                            </div>
                            <div>
                                <p class="text-xs mb-0 text-uppercase font-weight-bold text-muted">Providers With Dues</p>
                                <h5 class="font-weight-bolder mb-0 text-info">{{ $providersWithDues }}</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center">
                            <div class="icon icon-shape bg-gradient-success shadow text-center border-radius-md me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                <i class="bi bi-check-circle-fill text-lg text-white"></i>
                            </div>
                            <div>
                                <p class="text-xs mb-0 text-uppercase font-weight-bold text-muted">Total Registered Providers</p>
                                <h5 class="font-weight-bolder mb-0 text-success">{{ $totalProviders }}</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Filter Tabs & Search Bar --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <ul class="nav nav-pills" id="dueFilterTabs">
                        <li class="nav-item">
                            <a class="nav-link {{ empty($status) || $status === 'all' ? 'active bg-primary text-white fw-bold' : 'bg-light text-dark' }}" href="{{ route('admin.provider_dues.index') }}">
                                All Providers ({{ $totalProviders }})
                            </a>
                        </li>
                        <li class="nav-item ms-2">
                            <a class="nav-link {{ $status === 'blocked' ? 'active bg-danger text-white fw-bold' : 'bg-light text-dark' }}" href="{{ route('admin.provider_dues.index', ['status' => 'blocked']) }}">
                                Blocked ({{ $blockedProviders }})
                            </a>
                        </li>
                        <li class="nav-item ms-2">
                            <a class="nav-link {{ $status === 'has_due' ? 'active bg-warning text-dark fw-bold' : 'bg-light text-dark' }}" href="{{ route('admin.provider_dues.index', ['status' => 'has_due']) }}">
                                Has Dues ({{ $providersWithDues }})
                            </a>
                        </li>
                        <li class="nav-item ms-2">
                            <a class="nav-link {{ $status === 'clear' ? 'active bg-success text-white fw-bold' : 'bg-light text-dark' }}" href="{{ route('admin.provider_dues.index', ['status' => 'clear']) }}">
                                Zero Dues
                            </a>
                        </li>
                    </ul>

                    <form method="GET" action="{{ route('admin.provider_dues.index') }}" class="d-flex align-items-center">
                        <input type="hidden" name="status" value="{{ $status }}">
                        <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm me-2" placeholder="Search provider name, phone, code..." style="width: 250px;">
                        <button type="submit" class="btn btn-sm btn-primary mb-0">Search</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Providers Table --}}
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white pb-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 font-weight-bold">Providers Due Balances & Commission Status</h6>
                <span class="badge bg-secondary">Total Shown: {{ $providers->total() }}</span>
            </div>
            <div class="card-body px-4 pt-3 pb-3">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover mb-0 align-middle text-sm">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Provider</th>
                                <th>Phone / Code</th>
                                <th class="text-end">Pending Due Amount</th>
                                <th class="text-center">Threshold Limit</th>
                                <th class="text-center">Bidding Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($providers as $provider)
                                @php
                                    $due = (float) ($provider->payment_due ?? 0);
                                    $threshold = (float) ($provider->max_due_threshold ?? 25.0);
                                    $isBlocked = $due >= $threshold && $due > 0;
                                @endphp
                                <tr>
                                    <td>{{ $loop->iteration + ($providers->currentPage() - 1) * $providers->perPage() }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar avatar-sm me-2 bg-gradient-secondary rounded-circle text-white d-flex align-items-center justify-content-center">
                                                {{ strtoupper(substr($provider->name ?? 'P', 0, 1)) }}
                                            </div>
                                            <div>
                                                <h6 class="mb-0 text-sm font-weight-bold">{{ $provider->name }}</h6>
                                                <small class="text-muted">{{ $provider->email ?: 'No email' }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">{{ $provider->user_code ?: 'N/A' }}</span><br>
                                        <small class="text-muted">{{ $provider->phone }}</small>
                                    </td>
                                    <td class="text-end">
                                        @if ($due > 0)
                                            <span class="badge bg-danger fs-6 text-white">SAR {{ number_format($due, 2) }}</span>
                                        @else
                                            <span class="badge bg-success text-white">SAR 0.00</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold">SAR {{ number_format($threshold, 2) }}</span>
                                    </td>
                                    <td class="text-center">
                                        @if ($isBlocked)
                                            <span class="badge bg-danger"><i class="bi bi-slash-circle me-1"></i> Blocked (Exceeded)</span>
                                        @elseif ($due > 0)
                                            <span class="badge bg-warning text-dark"><i class="bi bi-clock-history me-1"></i> Active (Pending Dues)</span>
                                        @else
                                            <span class="badge bg-success"><i class="bi bi-check-all me-1"></i> Clear & Allowed</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('admin.provider_dues.show', $provider->id) }}" class="btn btn-xs btn-outline-primary mb-0 me-1">
                                            <i class="bi bi-journal-text me-1"></i> Ledger / Details
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="bi bi-info-circle fs-4 d-block mb-1"></i>
                                        No providers found matching this filter.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 d-flex justify-content-center">
                    {{ $providers->links() }}
                </div>
            </div>
        </div>

    </div>
</main>
@endsection
