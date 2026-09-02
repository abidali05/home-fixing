@extends('layouts.app')

@section('title', 'System Settings & Financial Configuration')

@push('css')
    <style>
        .settings-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease-in-out;
            background: #ffffff;
        }
        .settings-card-header {
            background: transparent;
            border-bottom: 1px solid #f0f2f5;
            padding: 1.25rem 1.5rem;
        }
        .settings-card-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #2b3674;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }
        .settings-icon-wrapper {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        .icon-general { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .icon-marketplace { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .icon-service { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .icon-gateway { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
        .icon-referral { background: rgba(236, 72, 153, 0.1); color: #ec4899; }
        .icon-banner { background: rgba(14, 165, 233, 0.1); color: #0ea5e9; }

        .form-section-title {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #8f9bba;
            margin-bottom: 1rem;
        }
        .form-label {
            font-weight: 600;
            color: #2b3674;
            font-size: 0.9rem;
            margin-bottom: 0.4rem;
        }
        .input-group-text {
            background-color: #f8fafc;
            border-color: #e2e8f0;
            color: #64748b;
            font-weight: 600;
        }
        .form-control, .form-select {
            border-color: #e2e8f0;
            padding: 0.65rem 0.9rem;
            font-size: 0.92rem;
            border-radius: 8px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }
        .helper-text {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 0.35rem;
        }
        .badge-vat {
            background-color: rgba(16, 185, 129, 0.15);
            color: #047857;
            font-weight: 700;
            font-size: 0.75rem;
            padding: 0.35rem 0.65rem;
            border-radius: 6px;
        }
        .preview-box {
            background-color: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            padding: 1.5rem;
        }
    </style>
@endpush

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            
            {{-- Header Title --}}
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-1" style="color: #1e293b;">System & Financial Settings</h4>
                    <p class="text-muted text-xs mb-0">Configure general platform settings, marketplace VAT taxes, service commissions, and payment gateway charges.</p>
                </div>
            </div>

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

            <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="row g-4">
                    {{-- Left Column: Form Settings --}}
                    <div class="col-lg-8">
                        
                        {{-- 1. General Settings --}}
                        <div class="card settings-card mb-4">
                            <div class="settings-card-header">
                                <h6 class="settings-card-title">
                                    <span class="settings-icon-wrapper icon-general"><i class="bi bi-gear-fill"></i></span>
                                    General Application Settings
                                </h6>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="system_name" class="form-label">System Name</label>
                                        <input type="text" class="form-control @error('system_name') is-invalid @enderror"
                                            name="system_name" id="system_name"
                                            value="{{ old('system_name', $settings->system_name ?? 'Azhl') }}" required>
                                        @error('system_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label for="system_logo" class="form-label">System Logo</label>
                                        <input type="file" class="form-control @error('logo') is-invalid @enderror"
                                            name="logo" id="system_logo" accept="image/*">
                                        @error('logo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label for="currency" class="form-label">Default Currency</label>
                                        <select name="currency" id="currency" class="form-select @error('currency') is-invalid @enderror" required>
                                            <option value="SAR" {{ old('currency', $settings->currency ?? 'SAR') == 'SAR' ? 'selected' : '' }}>SAR (Saudi Riyal)</option>
                                            <option value="USD" {{ old('currency', $settings->currency ?? '') == 'USD' ? 'selected' : '' }}>USD ($)</option>
                                            <option value="PKR" {{ old('currency', $settings->currency ?? '') == 'PKR' ? 'selected' : '' }}>PKR (Rs)</option>
                                        </select>
                                        @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label for="payment_method" class="form-label">Default Payment Method</label>
                                        <select name="payment_method" id="payment_method" class="form-select @error('payment_method') is-invalid @enderror" required>
                                            <option value="applepay" {{ old('payment_method', $settings->payment_method ?? 'applepay') == 'applepay' ? 'selected' : '' }}>Apple Pay</option>
                                            <option value="gpay" {{ old('payment_method', $settings->payment_method ?? '') == 'gpay' ? 'selected' : '' }}>Google Pay</option>
                                            <option value="sdc" {{ old('payment_method', $settings->payment_method ?? '') == 'sdc' ? 'selected' : '' }}>Credit / Mada Card (SDC)</option>
                                        </select>
                                        @error('payment_method') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 2. Marketplace Product VAT Settings --}}
                        <div class="card settings-card mb-4 border-start border-4 border-success">
                            <div class="settings-card-header d-flex justify-content-between align-items-center">
                                <h6 class="settings-card-title">
                                    <span class="settings-icon-wrapper icon-marketplace"><i class="bi bi-cart-check-fill"></i></span>
                                    Marketplace Product VAT & Tax Configuration
                                </h6>
                                <span class="badge-vat"><i class="bi bi-shield-check me-1"></i>15% Product VAT</span>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label for="marketplace_vat_percentage" class="form-label">Marketplace Product VAT Tax (%)</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" min="0" max="100" class="form-control form-control-lg fw-bold text-success @error('marketplace_vat_percentage') is-invalid @enderror"
                                                name="marketplace_vat_percentage" id="marketplace_vat_percentage"
                                                value="{{ old('marketplace_vat_percentage', $settings->marketplace_vat_percentage ?? 15.00) }}" placeholder="15.00" required>
                                            <span class="input-group-text font-weight-bold bg-success-soft text-success">%</span>
                                        </div>
                                        <div class="helper-text mt-2">
                                            <i class="bi bi-info-circle me-1 text-success"></i>
                                            <strong>Product VAT Rule:</strong> This 15% VAT tax applies to Marketplace product prices multiplied by quantity (e.g. 1 unit of 100 SAR product = 15 SAR VAT, 2 units of 100 SAR = 30 SAR VAT).
                                        </div>
                                        @error('marketplace_vat_percentage') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 3. Service Order Financials --}}
                        <div class="card settings-card mb-4 border-start border-4 border-warning">
                            <div class="settings-card-header">
                                <h6 class="settings-card-title">
                                    <span class="settings-icon-wrapper icon-service"><i class="bi bi-tools"></i></span>
                                    Service Order Platform & Commission Fees
                                </h6>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="customer_app_fee" class="form-label">Customer App Service Fee (SAR)</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" min="0" class="form-control @error('customer_app_fee') is-invalid @enderror"
                                                name="customer_app_fee" id="customer_app_fee"
                                                value="{{ old('customer_app_fee', $settings->customer_app_fee ?? 3.00) }}" placeholder="3.00" required>
                                            <span class="input-group-text">SAR</span>
                                        </div>
                                        <div class="helper-text">Added to customer repair subtotal at checkout (e.g. 3.00 SAR).</div>
                                        @error('customer_app_fee') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label for="azhl_fee" class="form-label">Provider Commission Fee (SAR)</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" min="0" class="form-control @error('azhl_fee') is-invalid @enderror"
                                                name="azhl_fee" id="azhl_fee"
                                                value="{{ old('azhl_fee', $settings->azhl_fee ?? 5.00) }}" placeholder="5.00" required>
                                            <span class="input-group-text">SAR</span>
                                        </div>
                                        <div class="helper-text">Commission fee deducted from service provider net earnings per order (e.g. 5.00 SAR).</div>
                                        @error('azhl_fee') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 4. Tap Payment Gateway Configuration --}}
                        <div class="card settings-card mb-4 border-start border-4 border-primary">
                            <div class="settings-card-header">
                                <h6 class="settings-card-title">
                                    <span class="settings-icon-wrapper icon-gateway"><i class="bi bi-credit-card-2-front-fill"></i></span>
                                    Payment Gateway Configuration (Tap Payment)
                                </h6>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="payment_gateway_fee_percentage" class="form-label">Gateway Fee (%)</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" min="0" max="100" class="form-control @error('payment_gateway_fee_percentage') is-invalid @enderror"
                                                name="payment_gateway_fee_percentage" id="payment_gateway_fee_percentage"
                                                value="{{ old('payment_gateway_fee_percentage', $settings->payment_gateway_fee_percentage ?? 2.50) }}" placeholder="2.50" required>
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <div class="helper-text">Percentage fee charged by Tap Payment (e.g. 2.50%).</div>
                                        @error('payment_gateway_fee_percentage') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="col-md-4">
                                        <label for="payment_gateway_fixed_fee" class="form-label">Fixed Transaction Fee (SAR)</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" min="0" class="form-control @error('payment_gateway_fixed_fee') is-invalid @enderror"
                                                name="payment_gateway_fixed_fee" id="payment_gateway_fixed_fee"
                                                value="{{ old('payment_gateway_fixed_fee', $settings->payment_gateway_fixed_fee ?? 1.00) }}" placeholder="1.00" required>
                                            <span class="input-group-text">SAR</span>
                                        </div>
                                        <div class="helper-text">Fixed fee per online checkout (e.g. 1.00 SAR).</div>
                                        @error('payment_gateway_fixed_fee') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="col-md-4">
                                        <label for="payment_gateway_vat_percentage" class="form-label">Gateway Fee VAT (%)</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" min="0" max="100" class="form-control @error('payment_gateway_vat_percentage') is-invalid @enderror"
                                                name="payment_gateway_vat_percentage" id="payment_gateway_vat_percentage"
                                                value="{{ old('payment_gateway_vat_percentage', $settings->payment_gateway_vat_percentage ?? 15.00) }}" placeholder="15.00" required>
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <div class="helper-text">VAT applied on the payment gateway fee (e.g. 15%).</div>
                                        @error('payment_gateway_vat_percentage') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 5. Referrals & Rewards --}}
                        <div class="card settings-card mb-4">
                            <div class="settings-card-header">
                                <h6 class="settings-card-title">
                                    <span class="settings-icon-wrapper icon-referral"><i class="bi bi-gift-fill"></i></span>
                                    Referral Program Rewards
                                </h6>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label for="referral_amount" class="form-label">Referral Bonus Amount (SAR)</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" min="0" class="form-control @error('referral_amount') is-invalid @enderror"
                                                name="referral_amount" id="referral_amount"
                                                value="{{ old('referral_amount', $settings->referral_amount ?? 10.00) }}" placeholder="10.00" required>
                                            <span class="input-group-text">SAR</span>
                                        </div>
                                        <div class="helper-text">Fixed reward credited to referrers upon successful referral (e.g. 10.00 SAR).</div>
                                        @error('referral_amount') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Save Button --}}
                        <div class="d-grid mb-4">
                            <button type="submit" class="btn btn-primary btn-lg shadow-sm py-3 font-weight-bold">
                                <i class="bi bi-check2-circle me-2"></i> Save & Apply All Settings
                            </button>
                        </div>
                    </div>

                    {{-- Right Column: Preview Box --}}
                    <div class="col-lg-4">
                        <div class="card settings-card sticky-top" style="top: 20px;">
                            <div class="settings-card-header">
                                <h6 class="settings-card-title">
                                    <i class="bi bi-eye-fill text-primary"></i> Configuration Summary
                                </h6>
                            </div>
                            <div class="card-body p-4">
                                <div class="preview-box text-center mb-4">
                                    <img id="logoPreview"
                                        src="{{ !empty($settings->logo) ? asset('uploads/system_settings/' . $settings->logo) : 'https://via.placeholder.com/150x160?text=System+Logo' }}"
                                        class="img-fluid rounded p-2 bg-white shadow-sm mb-3" style="max-height: 120px; object-fit: contain;">
                                    <h6 class="fw-bold mb-1" id="previewSystemName">{{ $settings->system_name ?? 'Azhl' }}</h6>
                                    <span class="badge bg-primary px-3 py-2 text-xs" id="previewCurrency">{{ $settings->currency ?? 'SAR' }}</span>
                                </div>

                                <ul class="list-group list-group-flush text-xs">
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span class="text-muted"><i class="bi bi-cart-fill text-success me-1"></i> Marketplace Product VAT:</span>
                                        <strong class="text-success fw-bold">{{ $settings->marketplace_vat_percentage ?? 15.00 }}%</strong>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span class="text-muted"><i class="bi bi-person-workspace text-warning me-1"></i> Customer App Fee:</span>
                                        <strong class="text-dark">{{ number_format($settings->customer_app_fee ?? 3.00, 2) }} SAR</strong>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span class="text-muted"><i class="bi bi-briefcase-fill text-warning me-1"></i> Provider Commission:</span>
                                        <strong class="text-dark">{{ number_format($settings->azhl_fee ?? 5.00, 2) }} SAR</strong>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span class="text-muted"><i class="bi bi-credit-card-fill text-primary me-1"></i> Gateway Fee:</span>
                                        <strong class="text-dark">{{ $settings->payment_gateway_fee_percentage ?? 2.50 }}% + {{ number_format($settings->payment_gateway_fixed_fee ?? 1.00, 2) }} SAR</strong>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span class="text-muted"><i class="bi bi-percent text-primary me-1"></i> Gateway VAT:</span>
                                        <strong class="text-dark">{{ $settings->payment_gateway_vat_percentage ?? 15.00 }}%</strong>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            {{-- Mobile Banners Section --}}
            <div class="row g-4 mt-2">
                <div class="col-lg-12">
                    <div class="card settings-card">
                        <div class="settings-card-header">
                            <h6 class="settings-card-title">
                                <span class="settings-icon-wrapper icon-banner"><i class="bi bi-images"></i></span>
                                Mobile App Banners & Marketplace Promotion
                            </h6>
                        </div>
                        <div class="card-body p-4">
                            <form action="{{ route('mobile_banners.update') }}" method="POST" enctype="multipart/form-data">
                                @csrf

                                {{-- Existing Banners --}}
                                <div class="mb-4 {{ $images->isEmpty() ? 'd-none' : '' }}">
                                    <label class="form-label">Active Mobile Banners</label>
                                    <div class="d-flex flex-wrap gap-3">
                                        @foreach ($images as $image)
                                            <div class="position-relative border rounded p-1 bg-white shadow-sm" style="width: 140px; height: 110px;">
                                                <img src="{{ asset('uploads/mobile_banners/' . $image->path) }}"
                                                    class="w-100 h-100 rounded" style="object-fit: cover;">
                                                @if ($image->showMarketplace && $image->marketplace)
                                                    <span class="badge bg-primary position-absolute bottom-0 start-0 w-100 rounded-bottom text-truncate" style="font-size: 9px; opacity: 0.95;">
                                                        {{ $image->marketplace->marketplaceProfile->shop_title ?? $image->marketplace->name }}
                                                    </span>
                                                @endif

                                                <a href="{{ route('mobile_banners.delete', $image->id) }}"
                                                    class="btn btn-sm btn-danger p-0 rounded-circle position-absolute top-0 end-0 m-1"
                                                    style="width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;"
                                                    onclick="return confirm('Delete this banner?')">
                                                    &times;
                                                </a>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Upload Section --}}
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Upload New Banners</label>
                                        <input type="file" name="banners[]" multiple accept="image/*" class="form-control @error('banners') is-invalid @enderror" required>
                                        <div class="helper-text">Select one or more banner images for the mobile app homepage.</div>
                                        @error('banners') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label for="showMarketplace" class="form-label">Marketplace Link Option</label>
                                        <div class="form-check form-switch mt-2">
                                            <input class="form-check-input" type="checkbox" name="showMarketplace" id="showMarketplace" value="1" {{ old('showMarketplace') ? 'checked' : '' }}>
                                            <label class="form-check-label fw-semibold ms-2" for="showMarketplace">Link Banner to Specific Marketplace</label>
                                        </div>
                                    </div>

                                    <div class="col-md-12 {{ old('showMarketplace') ? '' : 'd-none' }}" id="marketplace-select-container">
                                        <label for="marketplace_id" class="form-label">Select Target Marketplace Store</label>
                                        <select name="marketplace_id" id="marketplace_id" class="form-select @error('marketplace_id') is-invalid @enderror">
                                            <option value="">-- Choose Marketplace Store --</option>
                                            @foreach($marketplaces as $marketplace)
                                                <option value="{{ $marketplace->id }}" {{ old('marketplace_id') == $marketplace->id ? 'selected' : '' }}>
                                                    {{ $marketplace->marketplaceProfile->shop_title ?? $marketplace->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('marketplace_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="col-md-12 text-end mt-3">
                                        <button type="submit" class="btn btn-outline-primary shadow-sm font-weight-bold">
                                            <i class="bi bi-cloud-upload me-1"></i> Upload Banners
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
@endsection

@push('scripts')
    <script>
        document.getElementById('system_logo').addEventListener('change', function(e) {
            const [file] = e.target.files;
            if (file) {
                document.getElementById('logoPreview').src = URL.createObjectURL(file);
            }
        });

        document.getElementById('system_name').addEventListener('input', function() {
            document.getElementById('previewSystemName').innerText = this.value || 'Azhl';
        });

        document.getElementById('currency').addEventListener('change', function() {
            document.getElementById('previewCurrency').innerText = this.value;
        });

        const showMarketplaceCheckbox = document.getElementById('showMarketplace');
        const marketplaceSelectContainer = document.getElementById('marketplace-select-container');
        const marketplaceSelect = document.getElementById('marketplace_id');

        if (showMarketplaceCheckbox && marketplaceSelectContainer) {
            showMarketplaceCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    marketplaceSelectContainer.classList.remove('d-none');
                    marketplaceSelect.setAttribute('required', 'required');
                } else {
                    marketplaceSelectContainer.classList.add('d-none');
                    marketplaceSelect.removeAttribute('required');
                    marketplaceSelect.value = '';
                }
            });
        }
    </script>
@endpush
