@extends('layouts.app')

@section('title', 'Sellers')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm admin-panel-card">
                        <div class="card-header admin-panel-header">
                            <h6 class="admin-panel-title">Sellers</h6>
                            <p class="admin-panel-subtitle">Review seller accounts and shop details.</p>
                        </div>
                        <div class="card-body">
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

                            <form method="GET" action="{{ route('sellers.index') }}" class="row g-3 admin-filter-card admin-loader-form">
                                <div class="col-md-4">
                                    <label class="form-label">Search</label>
                                    <input type="text" name="search" class="form-control" value="{{ request('search') }}"
                                        placeholder="Name, email, phone, shop">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="">All Statuses</option>
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                                                {{ ucfirst($status) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Name</label>
                                    <input type="text" name="name" class="form-control" value="{{ request('name') }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Email</label>
                                    <input type="text" name="email" class="form-control" value="{{ request('email') }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" class="form-control" value="{{ request('phone') }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">From</label>
                                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">To</label>
                                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                                </div>
                                <div class="col-md-8 admin-filter-actions">
                                    <button type="submit" class="btn btn-primary mb-0">Apply Filters</button>
                                    <a href="{{ route('sellers.index') }}" class="btn btn-outline-secondary mb-0">Clear</a>
                                </div>
                            </form>

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle admin-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Seller</th>
                                            <th>Shop</th>
                                            <th>Phone</th>
                                            <th>Status</th>
                                            <th>Registered</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($sellers as $seller)
                                            @php
                                                $profile = $seller->marketplaceProfile;
                                                $badgeClass = match ($seller->marketplace_status) {
                                                    'active' => 'bg-success',
                                                    'inactive' => 'bg-secondary',
                                                    'suspended' => 'bg-warning',
                                                    'banned' => 'bg-danger',
                                                    default => 'bg-dark',
                                                };
                                            @endphp
                                            <tr>
                                                <td>#{{ $seller->id }}</td>
                                                <td>
                                                    <div class="fw-semibold">{{ $seller->name }}</div>
                                                    <small class="text-muted">{{ $seller->email ?: '-' }}</small>
                                                </td>
                                                <td>
                                                    <div>{{ $profile?->shop_title ?: '-' }}</div>
                                                    <small class="text-muted">{{ $profile?->tag_line ?: 'No tagline' }}</small>
                                                </td>
                                                <td>{{ $seller->phone }}</td>
                                                <td class="text-center admin-status-cell">
                                                    <form action="{{ route('sellers.status', $seller->id) }}" method="POST" class="admin-status-form admin-loader-form">
                                                        @csrf
                                                        <select name="marketplace_status" class="form-select form-select-sm admin-auto-submit">
                                                            @foreach ($statuses as $status)
                                                                <option value="{{ $status }}" {{ $seller->marketplace_status === $status ? 'selected' : '' }}>
                                                                    {{ ucfirst($status) }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </form>
                                                    {{-- <div class="mt-2">
                                                        <span class="badge admin-badge {{ $badgeClass }}">{{ ucfirst($seller->marketplace_status ?: 'inactive') }}</span>
                                                    </div> --}}
                                                </td>
                                                <td>{{ optional($seller->created_at)->format('d M Y') }}</td>
                                                <td class="text-center admin-action-cell">
                                                    <div class="admin-icon-actions">
                                                        <a href="{{ route('sellers.show', $seller->id) }}" class="admin-icon-btn info" title="View seller">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <a href="{{ route('sellers.edit', $seller->id) }}" class="admin-icon-btn primary" title="Edit seller">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">No sellers found for the selected filters.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <small class="admin-pagination-summary">
                                    Showing {{ $sellers->firstItem() ?? 0 }} to {{ $sellers->lastItem() ?? 0 }} of {{ $sellers->total() }} sellers
                                </small>
                                {{ $sellers->links('pagination::bootstrap-5') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
