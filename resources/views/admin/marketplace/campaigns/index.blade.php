@extends('layouts.app')

@section('title', 'Marketplace Campaigns')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm admin-panel-card">
                        <div class="card-header admin-panel-header d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="admin-panel-title">Campaign Management</h6>
                                <p class="admin-panel-subtitle">Control promotional campaigns linked to seller products.</p>
                            </div>
                            <a href="{{ route('marketplace.campaigns.create') }}" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus-lg me-1"></i> Add Campaign
                            </a>
                        </div>
                        <div class="card-body">
                            @if (session('success'))
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    {{ session('success') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

                            <form method="GET" action="{{ route('marketplace.campaigns.index') }}" class="row g-3 admin-filter-card admin-loader-form">
                                <div class="col-md-4">
                                    <label class="form-label">Search by Title</label>
                                    <input type="text" name="search" class="form-control" value="{{ request('search') }}">
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
                                    <label class="form-label">From</label>
                                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">To</label>
                                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                                </div>
                                <div class="col-md-2 admin-filter-actions">
                                    <button type="submit" class="btn btn-primary mb-0">Filter</button>
                                    <a href="{{ route('marketplace.campaigns.index') }}" class="btn btn-outline-secondary mb-0">Clear</a>
                                </div>
                            </form>

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle admin-table">
                                    <thead>
                                        <tr>
                                            <th>Image</th>
                                            <th>Title</th>
                                            <th>Product</th>
                                            <th>Seller</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th>Status</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($campaigns as $campaign)
                                            <tr>
                                                <td>
                                                    <img src="{{ !empty($campaign->campaign_image) ? asset('storage/' . $campaign->campaign_image) : asset('assets/img/default.jpg') }}"
                                                        class="admin-thumb" alt="{{ $campaign->title }}">
                                                </td>
                                                <td>
                                                    <div class="fw-semibold">{{ $campaign->title }}</div>
                                                    <small class="text-muted">{{ $campaign->subtitle ?: '-' }}</small>
                                                </td>
                                                <td>{{ $campaign->product?->product_name ?: '-' }}</td>
                                                <td>{{ $campaign->product?->seller?->marketplaceProfile?->shop_title ?: $campaign->product?->seller?->name ?: '-' }}</td>
                                                <td>{{ optional($campaign->start_date)->format('d M Y') }}</td>
                                                <td>{{ optional($campaign->end_date)->format('d M Y') }}</td>
                                                <td class="text-center admin-status-cell">
                                                    <form action="{{ route('marketplace.campaigns.status', $campaign->id) }}" method="POST" class="admin-status-form admin-loader-form">
                                                        @csrf
                                                        <select name="status" class="form-select form-select-sm admin-auto-submit">
                                                            @foreach ($statuses as $status)
                                                                <option value="{{ $status }}" {{ $campaign->status === $status ? 'selected' : '' }}>
                                                                    {{ ucfirst($status) }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </form>
                                                    {{-- <div class="mt-2">
                                                        <span class="badge admin-badge {{ $campaign->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                                            {{ ucfirst($campaign->status) }}
                                                        </span>
                                                    </div> --}}
                                                </td>
                                                <td class="text-center admin-action-cell">
                                                    <div class="admin-icon-actions">
                                                        <a href="{{ route('marketplace.campaigns.show', $campaign->id) }}" class="admin-icon-btn info" title="View campaign">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <a href="{{ route('marketplace.campaigns.edit', $campaign->id) }}" class="admin-icon-btn primary" title="Edit campaign">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </a>
                                                        <form action="{{ route('marketplace.campaigns.destroy', $campaign->id) }}" method="POST" class="admin-loader-form d-inline-block m-0" onsubmit="return confirm('Delete this campaign?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="admin-icon-btn danger" title="Delete campaign">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-4">No campaigns found.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <small class="admin-pagination-summary">
                                    Showing {{ $campaigns->firstItem() ?? 0 }} to {{ $campaigns->lastItem() ?? 0 }} of {{ $campaigns->total() }} campaigns
                                </small>
                                {{ $campaigns->links('pagination::bootstrap-5') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
