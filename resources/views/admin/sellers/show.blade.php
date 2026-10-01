@extends('layouts.app')

@section('title', 'Seller Details')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            @php
                $profile = $seller->marketplaceProfile;
            @endphp
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Seller Details</h6>
                            <div class="d-flex gap-2">
                                <a href="{{ route('sellers.edit', $seller->id) }}" class="btn btn-sm btn-primary">Edit</a>
                                <a href="{{ route('sellers.index') }}" class="btn btn-sm btn-secondary">Back</a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <div class="border rounded p-3">
                                        <img src="{{ !empty($profile?->shop_logo) ? asset('uploads/shop_logos/' . $profile->shop_logo) : asset('assets/img/default.jpg') }}"
                                            class="img-fluid rounded mb-3" alt="{{ $profile?->shop_title }}">
                                        @if (!empty($profile?->shop_banner_image))
                                            <img src="{{ asset('uploads/shop_banners/' . $profile->shop_banner_image) }}"
                                                class="img-fluid rounded" alt="Shop Banner">
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="row g-3">
                                         <div class="col-md-6"><strong>Name:</strong> {{ $seller->name }}</div>
                                         <div class="col-md-6"><strong>Phone:</strong> {{ $seller->phone }}</div>
                                        <div class="col-md-6"><strong>Account Status:</strong> {{ ucfirst($seller->marketplace_status ?: 'inactive') }}</div>
                                        <div class="col-md-6"><strong>Shop Title:</strong> {{ $profile?->shop_title ?: '-' }}</div>
                                        <div class="col-md-6"><strong>Tag Line:</strong> {{ $profile?->tag_line ?: '-' }}</div>
                                        <div class="col-md-6"><strong>Delivery Charges:</strong> {{ $profile?->delivery_charges ?? '-' }}</div>
                                        <div class="col-md-6"><strong>Shop Status:</strong> {{ ucfirst($profile?->shop_status ?: 'off') }}</div>
                                        <div class="col-md-6"><strong>Document Type:</strong> {{ $profile?->document_type ?: '-' }}</div>
                                        <div class="col-md-6"><strong>Document Number:</strong> {{ $profile?->document_number ?: '-' }}</div>
                                        <div class="col-md-6"><strong>Total Products:</strong> {{ $productCount }}</div>
                                        <div class="col-md-6"><strong>Total Orders:</strong> {{ $orderCount }}</div>
                                        <div class="col-md-6"><strong>Completed Sales:</strong> {{ number_format($totalSales, 2) }}</div>
                                        <div class="col-md-12"><strong>Bio:</strong> {{ $profile?->bio ?: '-' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
