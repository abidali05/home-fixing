@extends('layouts.app')

@section('title', 'Marketplace Products')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm admin-panel-card">
                        <div class="card-header admin-panel-header d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="admin-panel-title">Product Management</h6>
                                <p class="admin-panel-subtitle">Manage marketplace inventory, pricing, and visibility.</p>
                            </div>
                            <a href="{{ route('marketplace.products.create') }}" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus-lg me-1"></i> Add Product
                            </a>
                        </div>
                        <div class="card-body">
                            @if (session('success'))
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    {{ session('success') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

                            <form method="GET" action="{{ route('marketplace.products.index') }}" class="row g-3 admin-filter-card admin-loader-form">
                                <div class="col-md-3">
                                    <label class="form-label">Search</label>
                                    <input type="text" name="search" class="form-control" value="{{ request('search') }}"
                                        placeholder="Product name, SKU, seller">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Seller</label>
                                    <select name="seller_id" class="form-select select2">
                                        <option value="">All Sellers</option>
                                        @foreach ($sellers as $seller)
                                            <option value="{{ $seller->id }}" {{ (string) request('seller_id') === (string) $seller->id ? 'selected' : '' }}>
                                                {{ $seller->marketplaceProfile->shop_title ?? $seller->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Category</label>
                                    <select name="category_id" class="form-select select2">
                                        <option value="">All Categories</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}" {{ (string) request('category_id') === (string) $category->id ? 'selected' : '' }}>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
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
                                    <label class="form-label">Stock</label>
                                    <select name="stock_availability" class="form-select">
                                        <option value="">Any Stock</option>
                                        <option value="in_stock" {{ request('stock_availability') === 'in_stock' ? 'selected' : '' }}>In Stock</option>
                                        <option value="out_of_stock" {{ request('stock_availability') === 'out_of_stock' ? 'selected' : '' }}>Out of Stock</option>
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
                                <div class="col-md-8 admin-filter-actions">
                                    <button type="submit" class="btn btn-primary mb-0">Apply Filters</button>
                                    <a href="{{ route('marketplace.products.index') }}" class="btn btn-outline-secondary mb-0">Clear</a>
                                </div>
                            </form>

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle admin-table">
                                    <thead>
                                        <tr>
                                            <th>Image</th>
                                            <th>Product</th>
                                            <th>Category</th>
                                            <th>Seller</th>
                                            <th>Price</th>
                                            <th>Stock</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($products as $product)
                                            @php
                                                $badgeClass = match ($product->status) {
                                                    'publish' => 'bg-success',
                                                    'unpublish' => 'bg-secondary',
                                                    'pending' => 'bg-warning',
                                                    'trash' => 'bg-danger',
                                                    default => 'bg-dark',
                                                };
                                            @endphp
                                            <tr>
                                                <td>
                                                    <img src="{{ !empty($product->banner_image) ? asset('storage/' . $product->banner_image) : asset('assets/img/default.jpg') }}"
                                                        alt="{{ $product->product_name }}" class="admin-thumb">
                                                </td>
                                                <td>
                                                    <div class="fw-semibold">{{ $product->product_name }}</div>
                                                    <small class="text-muted">SKU: {{ $product->sku }}</small>
                                                </td>
                                                <td>{{ $product->category?->name ?: '-' }}</td>
                                                <td>{{ $product->seller?->marketplaceProfile?->shop_title ?: $product->seller?->name ?: '-' }}</td>
                                                <td>
                                                    <div class="fw-semibold">SAR {{ number_format($product->price, 2) }}</div>
                                                    <small class="text-muted">without VAT</small>
                                                </td>
                                                <td>{{ $product->total_stock }}</td>
                                                <td class="text-center admin-status-cell">
                                                    <form action="{{ route('marketplace.products.status', $product->id) }}" method="POST" class="admin-status-form admin-loader-form">
                                                        @csrf
                                                        <select name="status" class="form-select form-select-sm admin-auto-submit">
                                                            @foreach ($statuses as $status)
                                                                <option value="{{ $status }}" {{ $product->status === $status ? 'selected' : '' }}>
                                                                    {{ ucfirst($status) }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </form>
                                                    {{-- <div class="mt-2">
                                                        <span class="badge admin-badge {{ $badgeClass }}">{{ ucfirst($product->status) }}</span>
                                                    </div> --}}
                                                </td>
                                                <td>{{ optional($product->created_at)->format('d M Y') }}</td>
                                                <td class="text-center admin-action-cell">
                                                    <div class="admin-icon-actions">
                                                        <a href="{{ route('marketplace.products.show', $product->id) }}" class="admin-icon-btn info" title="View product">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <a href="{{ route('marketplace.products.edit', $product->id) }}" class="admin-icon-btn primary" title="Edit product">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </a>
                                                        <form action="{{ route('marketplace.products.destroy', $product->id) }}" method="POST" class="admin-loader-form d-inline-block m-0" onsubmit="return confirm('Move this product to trash?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="admin-icon-btn danger" title="Move to trash">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="9" class="text-center text-muted py-4">No products found.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <small class="admin-pagination-summary">
                                    Showing {{ $products->firstItem() ?? 0 }} to {{ $products->lastItem() ?? 0 }} of {{ $products->total() }} products
                                </small>
                                {{ $products->links('pagination::bootstrap-5') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
