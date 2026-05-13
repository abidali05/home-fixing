@extends('layouts.app')

@section('title', 'Marketplace Orders')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm admin-panel-card">
                        <div class="card-header admin-panel-header">
                            <h6 class="admin-panel-title">Marketplace Order Management</h6>
                            <p class="admin-panel-subtitle">Track customer orders, sellers, statuses, and payments.</p>
                        </div>
                        <div class="card-body">
                            @if (session('success'))
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    {{ session('success') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

                            <form method="GET" action="{{ route('marketplace.orders.index') }}" class="row g-3 admin-filter-card admin-loader-form">
                                <div class="col-md-3">
                                    <label class="form-label">Search</label>
                                    <input type="text" name="search" class="form-control" value="{{ request('search') }}"
                                        placeholder="Order ID, customer, seller">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Order Status</label>
                                    <select name="status" class="form-select">
                                        <option value="">All Statuses</option>
                                        @foreach ($orderStatuses as $status)
                                            <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                                                {{ ucwords(str_replace('_', ' ', $status)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Payment Status</label>
                                    <select name="payment_status" class="form-select">
                                        <option value="">All Payments</option>
                                        @foreach ($paymentStatuses as $paymentStatus)
                                            <option value="{{ $paymentStatus }}" {{ request('payment_status') === $paymentStatus ? 'selected' : '' }}>
                                                {{ ucfirst($paymentStatus) }}
                                            </option>
                                        @endforeach
                                    </select>
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
                                    <label class="form-label">Customer</label>
                                    <select name="customer_id" class="form-select select2">
                                        <option value="">All Customers</option>
                                        @foreach ($customers as $customer)
                                            <option value="{{ $customer->id }}" {{ (string) request('customer_id') === (string) $customer->id ? 'selected' : '' }}>
                                                {{ $customer->name }}
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
                                <div class="col-md-8 admin-filter-actions">
                                    <button type="submit" class="btn btn-primary mb-0">Apply Filters</button>
                                    <a href="{{ route('marketplace.orders.index') }}" class="btn btn-outline-secondary mb-0">Clear</a>
                                </div>
                            </form>

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle admin-table">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th>Sellers</th>
                                            <th>Total</th>
                                            <th>Payment</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($orders as $order)
                                            @php
                                                $sellerNames = $order->items->map(function ($item) {
                                                    return $item->shop?->marketplaceProfile?->shop_title ?: $item->shop?->name;
                                                })->filter()->unique()->values();
                                            @endphp
                                            <tr>
                                                <td>#{{ $order->order_number }}</td>
                                                <td>{{ $order->customer?->name ?: '-' }}</td>
                                                <td>{{ $sellerNames->isNotEmpty() ? $sellerNames->implode(', ') : '-' }}</td>
                                                <td>SAR {{ number_format($order->total_amount, 2) }}</td>
                                                <td>{{ ucfirst($order->payment_status ?? 'pending') }}</td>
                                                <td class="text-center admin-status-cell">
                                                    <form action="{{ route('marketplace.orders.update', $order->id) }}" method="POST" class="admin-status-form admin-loader-form">
                                                        @csrf
                                                        <input type="hidden" name="payment_status" value="{{ $order->payment_status ?? 'pending' }}">
                                                        <input type="hidden" name="redirect_back" value="1">
                                                        <select name="status" class="form-select form-select-sm admin-auto-submit">
                                                            @foreach ($orderStatuses as $status)
                                                                <option value="{{ $status }}" {{ $order->status === $status ? 'selected' : '' }}>
                                                                    {{ ucwords(str_replace('_', ' ', $status)) }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </form>
                                                </td>
                                                <td>{{ optional($order->created_at)->format('d M Y') }}</td>
                                                <td class="text-center admin-action-cell">
                                                    <div class="admin-icon-actions">
                                                        <a href="{{ route('marketplace.orders.show', $order->id) }}" class="admin-icon-btn info" title="View order">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-4">No marketplace orders found.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <small class="admin-pagination-summary">
                                    Showing {{ $orders->firstItem() ?? 0 }} to {{ $orders->lastItem() ?? 0 }} of {{ $orders->total() }} orders
                                </small>
                                {{ $orders->links('pagination::bootstrap-5') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
