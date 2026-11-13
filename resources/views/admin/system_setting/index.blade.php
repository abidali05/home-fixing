@extends('layouts.app')

@section('title', 'System Settings & Financial Configuration')

@push('styles')
    <style>
        .tab-content > .tab-pane {
            display: none !important;
        }
        .tab-content > .tab-pane.active {
            display: block !important;
        }
        .nav-tabs .nav-link {
            color: #64748b;
            border: none;
            border-bottom: 2px solid transparent;
            padding: 0.65rem 1.1rem;
            font-size: 0.88rem;
        }
        .nav-tabs .nav-link:hover {
            color: #4F2396;
            border-bottom: 2px solid #cbd5e1;
        }
        .nav-tabs .nav-link.active {
            color: #4F2396 !important;
            font-weight: 700 !important;
            border-bottom: 2px solid #4F2396 !important;
            background: transparent !important;
        }
    </style>
@endpush

@push('css')
    <style>
        .tab-content > .tab-pane {
            display: none !important;
        }
        .tab-content > .tab-pane.active {
            display: block !important;
        }
    </style>
@endpush

@section('content')
    <style>
        .tab-content > .tab-pane {
            display: none !important;
        }
        .tab-content > .tab-pane.active {
            display: block !important;
        }
        .settings-tab-pane {
            display: none !important;
        }
        .settings-tab-pane.active {
            display: block !important;
        }
    </style>

    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">

            {{-- Alerts --}}
            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show text-white" role="alert">
                    <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Please check the errors below:</strong>
                    <ul class="mb-0 mt-2 text-xs">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show text-white" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show text-white" role="alert">
                    <i class="bi bi-x-circle-fill me-2"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 text-dark font-weight-bold">System & Financial Settings</h6>
                                <p class="text-muted text-xs mb-0">Manage system branding, commissions, payment gateway fees, and app banners.</p>
                            </div>
                        </div>

                        {{-- Navigation Tabs --}}
                        <div class="px-4 pt-3">
                            <ul class="nav nav-tabs card-header-tabs" id="settingsTabs" role="tablist">
                                <li class="nav-item">
                                    <button class="nav-link active font-weight-bold" id="general-tab" data-bs-toggle="tab" data-bs-target="#general-pane" type="button" role="tab">
                                        <i class="bi bi-gear me-1"></i> General Settings
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link font-weight-bold" id="commissions-tab" data-bs-toggle="tab" data-bs-target="#commissions-pane" type="button" role="tab">
                                        <i class="bi bi-percent me-1"></i> Commissions & Platform Fees
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link font-weight-bold" id="gateway-tab" data-bs-toggle="tab" data-bs-target="#gateway-pane" type="button" role="tab">
                                        <i class="bi bi-credit-card me-1"></i> Payment Gateway
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link font-weight-bold" id="referral-tab" data-bs-toggle="tab" data-bs-target="#referral-pane" type="button" role="tab">
                                        <i class="bi bi-gift me-1"></i> Referral Rewards
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link font-weight-bold" id="banners-tab" data-bs-toggle="tab" data-bs-target="#banners-pane" type="button" role="tab">
                                        <i class="bi bi-images me-1"></i> Mobile Banners ({{ $images->count() }})
                                    </button>
                                </li>
                            </ul>
                        </div>

                        {{-- Main Global Settings Form --}}
                        <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data" id="mainSettingsForm">
                            @csrf
                            <input type="hidden" name="azhl_fee" value="{{ $settings->azhl_fee ?? 5.00 }}">
                        </form>

                        <div class="card-body px-4 pt-3 pb-4">
                            <div class="tab-content" id="settingsTabsContent">

                                {{-- 1. General Settings Tab (Direct child of tab-content) --}}
                                <div class="tab-pane fade show active settings-tab-pane" id="general-pane" role="tabpanel">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="system_name" class="form-label text-dark font-weight-bold text-xs">System Name <span class="text-danger">*</span></label>
                                            <input form="mainSettingsForm" type="text" class="form-control @error('system_name') is-invalid @enderror"
                                                name="system_name" id="system_name"
                                                value="{{ old('system_name', $settings->system_name ?? 'Azhl') }}" required>
                                            <small class="text-muted text-xs">The public application and brand name.</small>
                                            @error('system_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label for="currency" class="form-label text-dark font-weight-bold text-xs">Default Currency <span class="text-danger">*</span></label>
                                            <select form="mainSettingsForm" name="currency" id="currency" class="form-select @error('currency') is-invalid @enderror" required>
                                                <option value="SAR" {{ old('currency', $settings->currency ?? 'SAR') == 'SAR' ? 'selected' : '' }}>SAR - Saudi Riyal</option>
                                                <option value="USD" {{ old('currency', $settings->currency ?? '') == 'USD' ? 'selected' : '' }}>USD - US Dollar</option>
                                                <option value="PKR" {{ old('currency', $settings->currency ?? '') == 'PKR' ? 'selected' : '' }}>PKR - Pakistani Rupee</option>
                                            </select>
                                            <small class="text-muted text-xs">Primary currency used for all transactions.</small>
                                            @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label for="payment_method" class="form-label text-dark font-weight-bold text-xs">Default Payment Method <span class="text-danger">*</span></label>
                                            <select form="mainSettingsForm" name="payment_method" id="payment_method" class="form-select @error('payment_method') is-invalid @enderror" required>
                                                <option value="applepay" {{ old('payment_method', $settings->payment_method ?? 'applepay') == 'applepay' ? 'selected' : '' }}>Apple Pay & Cards (Tap Payments)</option>
                                                <option value="tap" {{ old('payment_method', $settings->payment_method ?? '') == 'tap' ? 'selected' : '' }}>Tap Payments (Gateway)</option>
                                                <option value="cash" {{ old('payment_method', $settings->payment_method ?? '') == 'cash' ? 'selected' : '' }}>Cash on Delivery</option>
                                            </select>
                                            <small class="text-muted text-xs">Default payment gateway activated for mobile checkout.</small>
                                            @error('payment_method') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label for="system_logo" class="form-label text-dark font-weight-bold text-xs">System Logo</label>
                                            <input form="mainSettingsForm" type="file" class="form-control @error('logo') is-invalid @enderror"
                                                name="logo" id="system_logo" accept="image/*">
                                            <small class="text-muted text-xs">Upload PNG, JPG, or SVG logo (max 2MB).</small>
                                            @error('logo') <div class="invalid-feedback">{{ $message }}</div> @enderror

                                            @if (!empty($settings->logo))
                                                <div class="mt-2 p-2 border rounded bg-light d-inline-flex align-items-center gap-2">
                                                    <img src="{{ asset('uploads/system_settings/' . $settings->logo) }}" alt="Logo" id="logoPreview" style="max-height: 40px; max-width: 120px;" class="img-fluid">
                                                    <small class="text-muted text-xs">Current Logo</small>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="mt-4 pt-3 border-top d-flex justify-content-end">
                                        <button form="mainSettingsForm" type="submit" class="btn btn-primary mb-0" style="background-color: #4F2396; border-color: #4F2396;">
                                            <i class="bi bi-check-lg me-1"></i> Save Settings
                                        </button>
                                    </div>
                                </div>

                                {{-- 2. Commissions & Platform Fees Tab (Direct child of tab-content) --}}
                                <div class="tab-pane fade settings-tab-pane" id="commissions-pane" role="tabpanel">
                                    <div class="row g-4">
                                        {{-- Services Section --}}
                                        <div class="col-lg-6">
                                            <div class="p-3 border rounded bg-light h-100">
                                                <h6 class="text-dark font-weight-bold mb-1">
                                                    <i class="bi bi-tools text-primary me-1"></i> Customer Maintenance & Repair Services
                                                </h6>
                                                <p class="text-muted text-xs mb-3">Commissions and customer app charges applied on service jobs.</p>

                                                <div class="mb-3">
                                                    <label for="azhl_percentage" class="form-label text-dark font-weight-bold text-xs">Provider Commission Fee (%) <span class="text-danger">*</span></label>
                                                    <div class="input-group">
                                                        <input form="mainSettingsForm" type="number" step="0.01" min="0" max="100" class="form-control @error('azhl_percentage') is-invalid @enderror"
                                                            name="azhl_percentage" id="azhl_percentage"
                                                            value="{{ old('azhl_percentage', $settings->azhl_percentage ?? 5.00) }}" placeholder="5.00" required>
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                    <small class="text-muted text-xs">Percentage deducted from provider's service earnings per job.</small>
                                                    @error('azhl_percentage') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                                </div>

                                                <div class="mb-2">
                                                    <label for="customer_app_fee" class="form-label text-dark font-weight-bold text-xs">Customer App Service Fee (SAR) <span class="text-danger">*</span></label>
                                                    <div class="input-group">
                                                        <input form="mainSettingsForm" type="number" step="0.01" min="0" class="form-control @error('customer_app_fee') is-invalid @enderror"
                                                            name="customer_app_fee" id="customer_app_fee"
                                                            value="{{ old('customer_app_fee', $settings->customer_app_fee ?? 0.00) }}" placeholder="0.00" required>
                                                        <span class="input-group-text">SAR</span>
                                                    </div>
                                                    <small class="text-muted text-xs">Fixed fee added to customer's repair total at checkout.</small>
                                                    @error('customer_app_fee') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Marketplace Section --}}
                                        <div class="col-lg-6">
                                            <div class="p-3 border rounded bg-light h-100">
                                                <h6 class="text-dark font-weight-bold mb-1">
                                                    <i class="bi bi-shop text-success me-1"></i> Marketplace Product Sales
                                                </h6>
                                                <p class="text-muted text-xs mb-3">Commissions and customer app charges applied on marketplace items.</p>

                                                <div class="mb-3">
                                                    <label for="marketplace_commission_percentage" class="form-label text-dark font-weight-bold text-xs">Seller Commission Fee (%) <span class="text-danger">*</span></label>
                                                    <div class="input-group">
                                                        <input form="mainSettingsForm" type="number" step="0.01" min="0" max="100" class="form-control @error('marketplace_commission_percentage') is-invalid @enderror"
                                                            name="marketplace_commission_percentage" id="marketplace_commission_percentage"
                                                            value="{{ old('marketplace_commission_percentage', $settings->marketplace_commission_percentage ?? 10.00) }}" placeholder="10.00" required>
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                    <small class="text-muted text-xs">Percentage deducted from seller product subtotal.</small>
                                                    @error('marketplace_commission_percentage') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                                </div>

                                                <div class="mb-2">
                                                    <label for="marketplace_customer_app_fee" class="form-label text-dark font-weight-bold text-xs">Marketplace App Fee (SAR) <span class="text-danger">*</span></label>
                                                    <div class="input-group">
                                                        <input form="mainSettingsForm" type="number" step="0.01" min="0" class="form-control @error('marketplace_customer_app_fee') is-invalid @enderror"
                                                            name="marketplace_customer_app_fee" id="marketplace_customer_app_fee"
                                                            value="{{ old('marketplace_customer_app_fee', $settings->marketplace_customer_app_fee ?? 3.00) }}" placeholder="3.00" required>
                                                        <span class="input-group-text">SAR</span>
                                                    </div>
                                                    <small class="text-muted text-xs">Fixed platform fee added to customer's marketplace cart checkout.</small>
                                                    @error('marketplace_customer_app_fee') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-4 pt-3 border-top d-flex justify-content-end">
                                        <button form="mainSettingsForm" type="submit" class="btn btn-primary mb-0" style="background-color: #4F2396; border-color: #4F2396;">
                                            <i class="bi bi-check-lg me-1"></i> Save Settings
                                        </button>
                                    </div>
                                </div>

                                {{-- 3. Payment Gateway (Tap Payments) Tab (Direct child of tab-content) --}}
                                <div class="tab-pane fade settings-tab-pane" id="gateway-pane" role="tabpanel">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="payment_gateway_fee_percentage" class="form-label text-dark font-weight-bold text-xs">Tap Gateway Processing Fee (%) <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <input form="mainSettingsForm" type="number" step="0.01" min="0" max="100" class="form-control @error('payment_gateway_fee_percentage') is-invalid @enderror"
                                                    name="payment_gateway_fee_percentage" id="payment_gateway_fee_percentage"
                                                    value="{{ old('payment_gateway_fee_percentage', $settings->payment_gateway_fee_percentage ?? 2.50) }}" placeholder="2.50" required>
                                                <span class="input-group-text">%</span>
                                            </div>
                                            <small class="text-muted text-xs">Transaction processing rate charged by Tap Payments.</small>
                                            @error('payment_gateway_fee_percentage') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label for="payment_gateway_vat_percentage" class="form-label text-dark font-weight-bold text-xs">Gateway Fee VAT (%) <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <input form="mainSettingsForm" type="number" step="0.01" min="0" max="100" class="form-control @error('payment_gateway_vat_percentage') is-invalid @enderror"
                                                    name="payment_gateway_vat_percentage" id="payment_gateway_vat_percentage"
                                                    value="{{ old('payment_gateway_vat_percentage', $settings->payment_gateway_vat_percentage ?? 15.00) }}" placeholder="15.00" required>
                                                <span class="input-group-text">%</span>
                                            </div>
                                            <small class="text-muted text-xs">Saudi VAT rate applied strictly on the payment gateway processing fee.</small>
                                            @error('payment_gateway_vat_percentage') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                        </div>
                                    </div>

                                    <div class="mt-4 pt-3 border-top d-flex justify-content-end">
                                        <button form="mainSettingsForm" type="submit" class="btn btn-primary mb-0" style="background-color: #4F2396; border-color: #4F2396;">
                                            <i class="bi bi-check-lg me-1"></i> Save Settings
                                        </button>
                                    </div>
                                </div>

                                {{-- 4. Referral Rewards Tab (Direct child of tab-content) --}}
                                <div class="tab-pane fade settings-tab-pane" id="referral-pane" role="tabpanel">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="referral_amount" class="form-label text-dark font-weight-bold text-xs">Referral Bonus Reward (SAR) <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <input form="mainSettingsForm" type="number" step="0.01" min="0" class="form-control @error('referral_amount') is-invalid @enderror"
                                                    name="referral_amount" id="referral_amount"
                                                    value="{{ old('referral_amount', $settings->referral_amount ?? 10.00) }}" placeholder="10.00" required>
                                                <span class="input-group-text">SAR</span>
                                            </div>
                                            <small class="text-muted text-xs">Bonus credited to user wallet balance when a referred friend completes an order.</small>
                                            @error('referral_amount') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                        </div>
                                    </div>

                                    <div class="mt-4 pt-3 border-top d-flex justify-content-end">
                                        <button form="mainSettingsForm" type="submit" class="btn btn-primary mb-0" style="background-color: #4F2396; border-color: #4F2396;">
                                            <i class="bi bi-check-lg me-1"></i> Save Settings
                                        </button>
                                    </div>
                                </div>

                                {{-- 5. Mobile Banners Tab (Direct child of tab-content) --}}
                                <div class="tab-pane fade settings-tab-pane" id="banners-pane" role="tabpanel">
                                    {{-- Upload New Banner Form --}}
                                    <form action="{{ route('mobile_banners.update') }}" method="POST" enctype="multipart/form-data" class="p-3 border rounded bg-light mb-4">
                                        @csrf
                                        <h6 class="text-dark font-weight-bold mb-1">
                                            <i class="bi bi-cloud-arrow-up text-primary me-1"></i> Upload New Mobile Banner
                                        </h6>
                                        <p class="text-muted text-xs mb-3">Add promotional banners displayed on the customer mobile app home screen.</p>

                                        <div class="row g-3 align-items-end">
                                            <div class="col-md-5">
                                                <label class="form-label text-dark font-weight-bold text-xs">Select Banner Images <span class="text-danger">*</span></label>
                                                <input type="file" name="banners[]" multiple accept="image/*" class="form-control @error('banners') is-invalid @enderror" required>
                                                @error('banners') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>

                                            <div class="col-md-3">
                                                <div class="form-check form-switch mb-2">
                                                    <input class="form-check-input" type="checkbox" name="showMarketplace" id="showMarketplace" value="1" {{ old('showMarketplace') ? 'checked' : '' }}>
                                                    <label class="form-check-label text-dark font-weight-bold text-xs" for="showMarketplace">Link to Marketplace Store</label>
                                                </div>
                                            </div>

                                            <div class="col-md-4 {{ old('showMarketplace') ? '' : 'd-none' }}" id="marketplace-select-container">
                                                <label for="marketplace_id" class="form-label text-dark font-weight-bold text-xs">Target Store</label>
                                                <select name="marketplace_id" id="marketplace_id" class="form-select @error('marketplace_id') is-invalid @enderror">
                                                    <option value="">-- Choose Store --</option>
                                                    @foreach($marketplaces as $marketplace)
                                                        <option value="{{ $marketplace->id }}" {{ old('marketplace_id') == $marketplace->id ? 'selected' : '' }}>
                                                            {{ $marketplace->marketplaceProfile->shop_title ?? $marketplace->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('marketplace_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>

                                            <div class="col-12 text-end">
                                                <button type="submit" class="btn btn-primary mb-0" style="background-color: #4F2396; border-color: #4F2396;">
                                                    <i class="bi bi-upload me-1"></i> Upload Banner
                                                </button>
                                            </div>
                                        </div>
                                    </form>

                                    {{-- Existing Banners List --}}
                                    <h6 class="text-dark font-weight-bold mb-3">Current Active Banners ({{ $images->count() }})</h6>
                                    @if($images->isNotEmpty())
                                        <div class="row g-3">
                                            @foreach($images as $image)
                                                <div class="col-md-4 col-sm-6">
                                                    <div class="card border shadow-none h-100">
                                                        <div class="position-relative">
                                                            <img src="{{ asset('uploads/mobile_banners/' . $image->path) }}" alt="Banner" class="card-img-top" style="height: 140px; object-fit: cover;">
                                                            <a href="{{ route('mobile_banners.delete', $image->id) }}"
                                                               class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 rounded-circle p-0"
                                                               style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;"
                                                               onclick="return confirm('Are you sure you want to delete this banner?');"
                                                               title="Delete Banner">
                                                                <i class="bi bi-trash-fill text-white" style="font-size: 13px;"></i>
                                                            </a>
                                                        </div>
                                                        <div class="card-body p-2 text-center bg-light">
                                                            @if($image->showMarketplace && $image->marketplace)
                                                                <span class="badge bg-success-soft text-success text-xxs font-weight-bold">
                                                                    <i class="bi bi-link-45deg me-1"></i>{{ $image->marketplace->marketplaceProfile->shop_title ?? $image->marketplace->name }}
                                                                </span>
                                                            @else
                                                                <span class="badge bg-secondary-soft text-secondary text-xxs">General Banner</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-center py-4 border rounded bg-light text-muted">
                                            <i class="bi bi-images d-block mb-2" style="font-size: 2rem;"></i>
                                            <p class="mb-0 text-sm">No mobile banners uploaded yet.</p>
                                        </div>
                                    @endif
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
@endsection

@push('scripts')
    <script>
        // System Logo live preview
        const logoInput = document.getElementById('system_logo');
        if (logoInput) {
            logoInput.addEventListener('change', function(e) {
                const [file] = e.target.files;
                if (file) {
                    const existingPreview = document.getElementById('logoPreview');
                    if (existingPreview) {
                        existingPreview.src = URL.createObjectURL(file);
                    }
                }
            });
        }

        // Marketplace select toggle
        const showMarketplaceCheckbox = document.getElementById('showMarketplace');
        const marketplaceSelectContainer = document.getElementById('marketplace-select-container');
        const marketplaceSelect = document.getElementById('marketplace_id');

        if (showMarketplaceCheckbox && marketplaceSelectContainer) {
            showMarketplaceCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    marketplaceSelectContainer.classList.remove('d-none');
                    if (marketplaceSelect) {
                        marketplaceSelect.setAttribute('required', 'required');
                    }
                } else {
                    marketplaceSelectContainer.classList.add('d-none');
                    if (marketplaceSelect) {
                        marketplaceSelect.removeAttribute('required');
                        marketplaceSelect.value = '';
                    }
                }
            });
        }

        // Failsafe JavaScript Tab Switcher (Ensures exact inline display styling)
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('#settingsTabs button[data-bs-toggle="tab"]');
            tabButtons.forEach(function(btn) {
                btn.addEventListener('shown.bs.tab', function(e) {
                    const targetSelector = e.target.getAttribute('data-bs-target');
                    document.querySelectorAll('.tab-content > .tab-pane').forEach(function(pane) {
                        pane.style.display = 'none';
                    });
                    const activePane = document.querySelector(targetSelector);
                    if (activePane) {
                        activePane.style.display = 'block';
                    }
                });
            });
        });
    </script>
@endpush
