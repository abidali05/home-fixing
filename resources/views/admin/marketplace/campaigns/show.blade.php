@extends('layouts.app')

@section('title', 'Campaign Details')

@push('styles')
    <style>
        .campaign-detail-card {
            border: 1px solid #e6edf3;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 18px 38px rgba(31, 53, 72, 0.08);
        }

        .campaign-detail-header {
            padding: 1.5rem 1.75rem;
            background: linear-gradient(135deg, #153447 0%, #1f6179 100%);
            color: #fff;
        }

        .campaign-detail-title {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 700;
            color: #ffffff;
        }

        .campaign-detail-subtitle {
            margin: 0.35rem 0 0;
            color: rgba(255, 255, 255, 0.72);
            font-size: 0.92rem;
        }

        .campaign-detail-body {
            padding: 1.65rem 1.75rem 1.75rem;
            background: #fff;
        }

        .campaign-hero-image {
            width: 100%;
            height: 320px;
            object-fit: cover;
            border-radius: 22px;
            border: 1px solid #e6edf3;
            background: #fff;
        }

        .campaign-status-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.45rem 0.85rem;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .campaign-status-chip.active {
            background: rgba(28, 184, 120, 0.14);
            color: #0c8a56;
        }

        .campaign-status-chip.inactive {
            background: rgba(107, 122, 136, 0.14);
            color: #5a6e7d;
        }

        .campaign-meta-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
            margin-top: 1.25rem;
        }

        .campaign-meta-card,
        .campaign-side-card,
        .campaign-copy-box {
            border: 1px solid #e6edf3;
            border-radius: 18px;
            padding: 1rem 1.1rem;
            background: #fbfdff;
        }

        .campaign-meta-label {
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #708392;
            margin-bottom: 0.3rem;
        }

        .campaign-meta-value {
            color: #233846;
            font-weight: 600;
        }

        .campaign-section-title {
            margin: 1.45rem 0 0.85rem;
            font-size: 0.92rem;
            font-weight: 700;
            color: #1c3343;
        }

        .campaign-side-title {
            margin: 0 0 0.9rem;
            font-size: 0.95rem;
            font-weight: 700;
            color: #1c3343;
        }

        .campaign-spec-list {
            display: grid;
            gap: 0.75rem;
        }

        .campaign-spec-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px dashed #e1e9ef;
        }

        .campaign-spec-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .campaign-spec-label {
            color: #6d8090;
            font-size: 0.84rem;
        }

        .campaign-spec-value {
            color: #223644;
            font-weight: 600;
            text-align: end;
        }

        .campaign-product-card {
            display: flex;
            align-items: center;
            gap: 0.9rem;
            border: 1px solid #e6edf3;
            border-radius: 18px;
            padding: 0.95rem 1rem;
            background: #fcfdff;
        }

        .campaign-product-thumb {
            width: 74px;
            height: 74px;
            object-fit: cover;
            border-radius: 16px;
            border: 1px solid #e6edf3;
            background: #fff;
        }

        @media (max-width: 991.98px) {
            .campaign-meta-grid {
                grid-template-columns: 1fr;
            }

            .campaign-hero-image {
                height: 240px;
            }
        }
    </style>
@endpush

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card campaign-detail-card">
                        <div class="campaign-detail-header d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <h6 class="campaign-detail-title">{{ $campaign->title }}</h6>
                                <p class="campaign-detail-subtitle">{{ $campaign->subtitle ?: 'No subtitle added for this campaign.' }}</p>
                            </div>
                            <div class="d-flex gap-2 flex-wrap justify-content-end">
                                <span class="campaign-status-chip {{ $campaign->status === 'active' ? 'active' : 'inactive' }}">
                                    {{ ucfirst($campaign->status) }}
                                </span>
                                <a href="{{ route('marketplace.campaigns.edit', $campaign->id) }}" class="btn btn-sm btn-primary">Edit</a>
                                <a href="{{ route('marketplace.campaigns.index') }}" class="btn btn-sm btn-secondary">Back</a>
                            </div>
                        </div>
                        <div class="campaign-detail-body">
                            <div class="row g-4 align-items-start">
                                <div class="col-md-5">
                                    <img src="{{ !empty($campaign->campaign_image) ? asset('storage/' . $campaign->campaign_image) : asset('assets/img/default.jpg') }}"
                                        class="campaign-hero-image" alt="{{ $campaign->title }}">
                                </div>
                                <div class="col-md-7">
                                    <div class="campaign-meta-grid">
                                        <div class="campaign-meta-card">
                                            <span class="campaign-meta-label">Start Date</span>
                                            <span class="campaign-meta-value">{{ optional($campaign->start_date)->format('d M Y') ?: '-' }}</span>
                                        </div>
                                        <div class="campaign-meta-card">
                                            <span class="campaign-meta-label">End Date</span>
                                            <span class="campaign-meta-value">{{ optional($campaign->end_date)->format('d M Y') ?: '-' }}</span>
                                        </div>
                                        <div class="campaign-meta-card">
                                            <span class="campaign-meta-label">Duration</span>
                                            <span class="campaign-meta-value">
                                                @if ($campaign->start_date && $campaign->end_date)
                                                    {{ \Carbon\Carbon::parse($campaign->start_date)->diffInDays(\Carbon\Carbon::parse($campaign->end_date)) + 1 }} days
                                                @else
                                                    -
                                                @endif
                                            </span>
                                        </div>
                                        <div class="campaign-meta-card">
                                            <span class="campaign-meta-label">Created On</span>
                                            <span class="campaign-meta-value">{{ optional($campaign->created_at)->format('d M Y, h:i A') ?: '-' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <h6 class="campaign-section-title">Campaign Copy</h6>
                            <div class="campaign-copy-box">
                                <div class="fw-semibold text-dark mb-2">Title</div>
                                <div class="mb-3">{{ $campaign->title }}</div>
                                <div class="fw-semibold text-dark mb-2">Subtitle</div>
                                <div>{{ $campaign->subtitle ?: 'No subtitle available.' }}</div>
                            </div>

                            <h6 class="campaign-section-title">Linked Product</h6>
                            <div class="campaign-product-card">
                                <img src="{{ !empty($campaign->product?->banner_image) ? asset('storage/' . $campaign->product->banner_image) : asset('assets/img/default.jpg') }}"
                                    class="campaign-product-thumb" alt="{{ $campaign->product?->product_name }}">
                                <div>
                                    <div class="fw-semibold text-dark">{{ $campaign->product?->product_name ?: '-' }}</div>
                                    <div class="text-muted small">Category: {{ $campaign->product?->category?->name ?: '-' }}</div>
                                    <div class="text-muted small">
                                        Seller: {{ $campaign->product?->seller?->marketplaceProfile?->shop_title ?: $campaign->product?->seller?->name ?: '-' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="campaign-side-card">
                        <h6 class="campaign-side-title">Campaign Summary</h6>
                        <div class="campaign-spec-list">
                            <div class="campaign-spec-row">
                                <span class="campaign-spec-label">Campaign ID</span>
                                <span class="campaign-spec-value">#{{ $campaign->id }}</span>
                            </div>
                            <div class="campaign-spec-row">
                                <span class="campaign-spec-label">Status</span>
                                <span class="campaign-spec-value">{{ ucfirst($campaign->status) }}</span>
                            </div>
                            <div class="campaign-spec-row">
                                <span class="campaign-spec-label">Product ID</span>
                                <span class="campaign-spec-value">#{{ $campaign->product_id ?: '-' }}</span>
                            </div>
                            <div class="campaign-spec-row">
                                <span class="campaign-spec-label">Product Price</span>
                                <span class="campaign-spec-value">
                                    {{ $campaign->product ? 'SAR ' . number_format($campaign->product->price, 2) : '-' }}
                                </span>
                            </div>
                            <div class="campaign-spec-row">
                                <span class="campaign-spec-label">Sale Price</span>
                                <span class="campaign-spec-value">
                                    {{ $campaign->product && $campaign->product->sale_price ? 'SAR ' . number_format($campaign->product->sale_price, 2) : '-' }}
                                </span>
                            </div>
                            <div class="campaign-spec-row">
                                <span class="campaign-spec-label">Campaign Product Flag</span>
                                <span class="campaign-spec-value">{{ $campaign->product?->is_campaign ? 'Enabled' : 'Disabled' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
