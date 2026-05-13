@extends('layouts.app')

@section('title', 'Product Details')

@push('styles')
    <style>
        .product-detail-card {
            border: 1px solid #e6edf3;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 18px 38px rgba(31, 53, 72, 0.08);
        }

        .product-detail-header {
            padding: 1.5rem 1.75rem;
            background: linear-gradient(135deg, #173042 0%, #24566d 100%);
            color: #fff;
        }

        .product-detail-title {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 700;
            color: #ffffff;
        }

        .product-detail-subtitle {
            margin: 0.35rem 0 0;
            color: rgba(255, 255, 255, 0.72);
            font-size: 0.92rem;
        }

        .product-detail-body {
            padding: 1.6rem 1.75rem 1.75rem;
            background: #fff;
        }

        .product-hero-image {
            width: 100%;
            height: 340px;
            object-fit: cover;
            border-radius: 22px;
            border: 1px solid #e6edf3;
            background: #fff;
        }

        .product-price-card {
            border: 1px solid #e2ebf1;
            border-radius: 20px;
            padding: 1.1rem 1.15rem;
            background: linear-gradient(180deg, #f9fcfe 0%, #ffffff 100%);
            height: 100%;
        }

        .product-price-label {
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #6f8190;
            margin-bottom: 0.35rem;
        }

        .product-price-value {
            font-size: 1.4rem;
            font-weight: 800;
            color: #183244;
            line-height: 1.2;
        }

        .product-price-note {
            margin-top: 0.3rem;
            color: #7c8d9a;
            font-size: 0.83rem;
        }

        .product-info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.95rem;
            margin-top: 1.25rem;
        }

        .product-info-item,
        .product-side-card {
            border: 1px solid #e6edf3;
            border-radius: 18px;
            padding: 1rem 1.1rem;
            background: #fbfdff;
        }

        .product-info-label {
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #708392;
            margin-bottom: 0.3rem;
        }

        .product-info-value {
            color: #243846;
            font-weight: 600;
        }

        .product-section-title {
            margin: 1.5rem 0 0.85rem;
            font-size: 0.92rem;
            font-weight: 700;
            color: #1c3343;
        }

        .product-description-box {
            border: 1px solid #e6edf3;
            border-radius: 18px;
            padding: 1rem 1.1rem;
            background: #fcfdff;
            color: #3b4e5c;
            line-height: 1.75;
        }

        .product-gallery-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.9rem;
        }

        .product-gallery-item {
            border: 1px solid #e6edf3;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
        }

        .product-gallery-item img {
            width: 100%;
            height: 140px;
            object-fit: cover;
            display: block;
        }

        .product-side-card-title {
            margin: 0 0 0.85rem;
            font-size: 0.92rem;
            font-weight: 700;
            color: #1c3343;
        }

        .product-spec-list {
            display: grid;
            gap: 0.75rem;
        }

        .product-spec-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px dashed #e1e9ef;
        }

        .product-spec-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .product-spec-label {
            color: #6d8090;
            font-size: 0.84rem;
        }

        .product-spec-value {
            color: #223644;
            font-weight: 600;
            text-align: end;
        }

        @media (max-width: 991.98px) {
            .product-info-grid,
            .product-gallery-grid {
                grid-template-columns: 1fr;
            }

            .product-hero-image {
                height: 260px;
            }
        }
    </style>
@endpush

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            @php
                $images = [];

                if (is_array($product->product_images)) {
                    $images = array_values(array_filter($product->product_images));
                } elseif (is_string($product->product_images) && $product->product_images !== '') {
                    $decodedImages = json_decode($product->product_images, true);
                    $images = json_last_error() === JSON_ERROR_NONE && is_array($decodedImages)
                        ? array_values(array_filter($decodedImages))
                        : array_values(array_filter(explode(',', $product->product_images)));
                }
            @endphp
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card product-detail-card">
                        <div class="product-detail-header d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <h6 class="product-detail-title">{{ $product->product_name }}</h6>
                                <p class="product-detail-subtitle">
                                    {{ $product->category?->name ?: 'Uncategorized' }} ·
                                    Seller: {{ $product->seller?->marketplaceProfile?->shop_title ?: $product->seller?->name ?: '-' }}
                                </p>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('marketplace.products.edit', $product->id) }}" class="btn btn-sm btn-primary">Edit</a>
                                <a href="{{ route('marketplace.products.index') }}" class="btn btn-sm btn-secondary">Back</a>
                            </div>
                        </div>
                        <div class="product-detail-body">
                            <div class="row g-4 align-items-start">
                                <div class="col-md-4">
                                    <img src="{{ !empty($product->banner_image) ? asset('storage/' . $product->banner_image) : asset('assets/img/default.jpg') }}"
                                        class="product-hero-image" alt="{{ $product->product_name }}">
                                </div>
                                <div class="col-md-8">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="product-price-card">
                                                <div class="product-price-label">Price</div>
                                                <div class="product-price-value">SAR {{ number_format($product->price, 2) }}</div>
                                                <div class="product-price-note">without VAT</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="product-price-card">
                                                <div class="product-price-label">Sale Price</div>
                                                <div class="product-price-value">
                                                    {{ $product->sale_price ? 'SAR ' . number_format($product->sale_price, 2) : '-' }}
                                                </div>
                                                <div class="product-price-note">without VAT</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="product-info-grid">
                                        <div class="product-info-item">
                                            <span class="product-info-label">Seller</span>
                                            <span class="product-info-value">{{ $product->seller?->marketplaceProfile?->shop_title ?: $product->seller?->name ?: '-' }}</span>
                                        </div>
                                        <div class="product-info-item">
                                            <span class="product-info-label">Category</span>
                                            <span class="product-info-value">{{ $product->category?->name ?: '-' }}</span>
                                        </div>
                                        <div class="product-info-item">
                                            <span class="product-info-label">Status</span>
                                            <span class="product-info-value">{{ ucfirst($product->status) }}</span>
                                        </div>
                                        <div class="product-info-item">
                                            <span class="product-info-label">Available Stock</span>
                                            <span class="product-info-value">{{ $product->total_stock }}</span>
                                        </div>
                                        <div class="product-info-item">
                                            <span class="product-info-label">SKU</span>
                                            <span class="product-info-value">{{ $product->sku }}</span>
                                        </div>
                                        <div class="product-info-item">
                                            <span class="product-info-label">Tax Status</span>
                                            <span class="product-info-value">{{ $product->tax_status }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <h6 class="product-section-title">Description</h6>
                            <div class="product-description-box">
                                {{ $product->product_description ?: 'No description available.' }}
                            </div>

                            <h6 class="product-section-title">Installation Details</h6>
                            <div class="product-description-box">
                                {{ $product->installation_details ?: 'No installation details provided.' }}
                            </div>

                            <h6 class="product-section-title">Additional Images</h6>
                            <div class="product-gallery-grid">
                                @forelse ($images as $image)
                                    <div class="product-gallery-item">
                                        <img src="{{ asset('storage/' . $image) }}" alt="Product image">
                                    </div>
                                @empty
                                    <div class="text-muted">No additional product images.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="product-side-card">
                        <h6 class="product-side-card-title">Specifications</h6>
                        <div class="product-spec-list">
                            <div class="product-spec-row">
                                <span class="product-spec-label">Discount Type</span>
                                <span class="product-spec-value">{{ $product->discount_type ?: 'NA' }}</span>
                            </div>
                            <div class="product-spec-row">
                                <span class="product-spec-label">Discount Value</span>
                                <span class="product-spec-value">{{ $product->discount_value ? 'SAR ' . number_format($product->discount_value, 2) : 'NA' }}</span>
                            </div>
                            <div class="product-spec-row">
                                <span class="product-spec-label">Installation Available</span>
                                <span class="product-spec-value">{{ $product->installation_available ? 'Yes' : 'No' }}</span>
                            </div>
                            <div class="product-spec-row">
                                <span class="product-spec-label">Installation Price</span>
                                <span class="product-spec-value">{{ $product->installation_price ? 'SAR ' . number_format($product->installation_price, 2) : 'NA' }}</span>
                            </div>
                            <div class="product-spec-row">
                                <span class="product-spec-label">Weight</span>
                                <span class="product-spec-value">{{ $product->weight ?: '-' }}</span>
                            </div>
                            <div class="product-spec-row">
                                <span class="product-spec-label">Height</span>
                                <span class="product-spec-value">{{ $product->height ?: '-' }}</span>
                            </div>
                            <div class="product-spec-row">
                                <span class="product-spec-label">Width</span>
                                <span class="product-spec-value">{{ $product->width ?: '-' }}</span>
                            </div>
                            <div class="product-spec-row">
                                <span class="product-spec-label">Length</span>
                                <span class="product-spec-value">{{ $product->length ?: '-' }}</span>
                            </div>
                            <div class="product-spec-row">
                                <span class="product-spec-label">Limited Stock</span>
                                <span class="product-spec-value">{{ $product->limited_stock ?: '-' }}</span>
                            </div>
                            <div class="product-spec-row">
                                <span class="product-spec-label">In Campaign</span>
                                <span class="product-spec-value">{{ $product->is_campaign ? 'Yes' : 'No' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
