@extends('layouts.app')

@section('title', 'Company Profile & Statistics')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            
            <div class="row align-items-center mb-4">
                <div class="col-md-8">
                    <h5 class="mb-0 text-dark font-weight-bold" style="font-size: 1.3rem;">{{ $company->company_name }}</h5>
                    <p class="text-muted text-xs mb-0">Corporate profile dashboard and operational metrics.</p>
                </div>
                <div class="col-md-4 text-md-end mt-2 mt-md-0">
                    <a href="{{ route('companies.index') }}" class="btn btn-sm btn-secondary border-radius-lg px-3">
                        <i class="bi bi-arrow-left me-1"></i> Back to Companies
                    </a>
                </div>
            </div>

            <!-- Statistics Section -->
            <div class="row mb-4">
                <div class="col-xl-4 col-sm-6 mb-xl-0 mb-4">
                    <div class="card shadow-sm border-0" style="border-radius: 16px; background-color: #173042;">
                        <div class="card-body p-3">
                            <div class="row">
                                <div class="col-8">
                                    <div class="numbers">
                                        <p class="text-xs text-white opacity-7 text-uppercase font-weight-bold">Service Providers</p>
                                        <h4 class="font-weight-bolder text-white mb-0">{{ $providersCount }}</h4>
                                    </div>
                                </div>
                                <div class="col-4 text-end">
                                    <div class="icon icon-shape bg-gradient-info shadow-info text-center rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #4F2396 !important;">
                                        <i class="bi bi-people-fill text-lg opacity-10 text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-sm-6 mb-xl-0 mb-4">
                    <div class="card shadow-sm border-0" style="border-radius: 16px; background-color: #173042;">
                        <div class="card-body p-3">
                            <div class="row">
                                <div class="col-8">
                                    <div class="numbers">
                                        <p class="text-xs text-white opacity-7 text-uppercase font-weight-bold">Bids Submitted</p>
                                        <h4 class="font-weight-bolder text-white mb-0">{{ $bidsCount }}</h4>
                                    </div>
                                </div>
                                <div class="col-4 text-end">
                                    <div class="icon icon-shape bg-gradient-warning shadow-warning text-center rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #f5a623 !important;">
                                        <i class="bi bi-file-earmark-text-fill text-lg opacity-10 text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-sm-6">
                    <div class="card shadow-sm border-0" style="border-radius: 16px; background-color: #173042;">
                        <div class="card-body p-3">
                            <div class="row">
                                <div class="col-8">
                                    <div class="numbers">
                                        <p class="text-xs text-white opacity-7 text-uppercase font-weight-bold">Service Orders / Requests</p>
                                        <h4 class="font-weight-bolder text-white mb-0">{{ $serviceRequestsCount }}</h4>
                                    </div>
                                </div>
                                <div class="col-4 text-end">
                                    <div class="icon icon-shape bg-gradient-success shadow-success text-center rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #2ec4b6 !important;">
                                        <i class="bi bi-briefcase-fill text-lg opacity-10 text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Company Details Card -->
                <div class="col-lg-4 mb-4">
                    <div class="card shadow-sm border-0 h-100" style="border-radius: 20px;">
                        <div class="card-header bg-transparent pb-0">
                            <h6 class="text-dark font-weight-bold mb-0">Company Details</h6>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item px-0 border-0 d-flex justify-content-between text-sm">
                                    <strong class="text-dark">Company Name:</strong>
                                    <span class="text-muted">{{ $company->company_name }}</span>
                                </li>
                                <li class="list-group-item px-0 border-0 d-flex justify-content-between text-sm">
                                    <strong class="text-dark">Contact Person:</strong>
                                    <span class="text-muted">{{ $company->name }}</span>
                                </li>
                                <li class="list-group-item px-0 border-0 d-flex justify-content-between text-sm">
                                    <strong class="text-dark">Email Address:</strong>
                                    <span class="text-muted">{{ $company->email }}</span>
                                </li>
                                <li class="list-group-item px-0 border-0 d-flex justify-content-between text-sm">
                                    <strong class="text-dark">Phone Number:</strong>
                                    <span class="text-muted">{{ $company->phone }}</span>
                                </li>
                                <li class="list-group-item px-0 border-0 d-flex justify-content-between text-sm">
                                    <strong class="text-dark">Office Address:</strong>
                                    <span class="text-muted text-end" style="max-width: 60%;">{{ $company->address ?? 'N/A' }}</span>
                                </li>
                                <li class="list-group-item px-0 border-0 d-flex justify-content-between text-sm">
                                    <strong class="text-dark">Status:</strong>
                                    <span class="badge bg-soft-success text-success" style="background-color: #e2f9ec;">{{ ucfirst($company->status) }}</span>
                                </li>
                                <li class="list-group-item px-0 border-0 d-flex justify-content-between text-sm">
                                    <strong class="text-dark">Registered Date:</strong>
                                    <span class="text-muted">{{ $company->created_at->format('d M Y') }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Associated Providers Table -->
                <div class="col-lg-8 mb-4">
                    <div class="card shadow-sm border-0 h-100" style="border-radius: 20px;">
                        <div class="card-header bg-transparent pb-0 d-flex justify-content-between align-items-center">
                            <h6 class="text-dark font-weight-bold mb-0">Associated Service Providers</h6>
                            @if(!optional(Auth::guard('admin')->user())->is_company)
                                <a href="{{ route('companies.assign.form', $company->id) }}" class="btn btn-sm btn-outline-primary border-radius-lg">
                                    <i class="bi bi-pencil-square me-1"></i> Edit Association
                                </a>
                            @endif
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table align-middle text-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Provider</th>
                                            <th>Phone</th>
                                            <th class="text-center">Active Orders</th>
                                            <th class="text-center">Earnings</th>
                                            <th>Status</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($assignedProviders as $prov)
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar avatar-sm me-3 bg-light rounded-circle text-center d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                            @if($prov->profile_image)
                                                                <img src="{{ $prov->profile_image }}" class="rounded-circle w-100 h-100" style="object-fit: cover;">
                                                            @else
                                                                <span class="text-xs font-weight-bold text-secondary">{{ strtoupper(substr($prov->name, 0, 2)) }}</span>
                                                            @endif
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-0 text-xs font-weight-bold">{{ $prov->name }}</h6>
                                                            <small class="text-muted text-xxs">{{ $prov->email }}</small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-xs font-weight-semibold text-secondary">{{ $prov->phone }}</td>
                                                <td class="text-center text-xs font-weight-bold text-dark">{{ App\Models\Orders::where('provider_id', $prov->id)->count() }}</td>
                                                <td class="text-center text-xs font-weight-bold text-success">{{ $prov->total_earnings }} SAR</td>
                                                <td>
                                                    <span class="badge bg-soft-success text-success" style="background-color: #e2f9ec;">{{ ucfirst($prov->status) }}</span>
                                                </td>
                                                <td class="text-center">
                                                    <a href="{{ route('providers.show', $prov->id) }}" class="btn btn-xs btn-outline-primary border-radius-lg py-1 px-2">
                                                        <i class="bi bi-eye me-1"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">No service providers assigned under this company yet.</td>
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
@endsection
