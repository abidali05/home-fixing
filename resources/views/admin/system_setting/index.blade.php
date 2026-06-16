@extends('layouts.app')

@section('title', 'System Settings')

@section('content')
    @php
        $rolePermissions = App\Models\Admin\RolePermissions::where('role_id', Auth::guard('admin')->user()->role)
            ->pluck('permission_id')
            ->toArray();
        $allowed_modules = App\Models\Admin\Permission::whereIn('id', $rolePermissions)
            ->pluck('module_name')
            ->unique()
            ->toArray();
    @endphp
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-lg-12">
                    <div class="card shadow border-0">
                        <div class="card-header bg-white border-bottom-0">
                            <h6 class="mb-0">System Configuration</h6>
                        </div>
                        <div class="card-body pt-0">
                            <div class="row">
                                {{-- Form Section (Left) --}}
                                <div class="col-md-7 pe-4 border-end">
                                    <form action="{{ route('settings.update') }}" method="POST"
                                        enctype="multipart/form-data">
                                        @csrf

                                        {{-- Display global validation errors --}}
                                        @if ($errors->any())
                                            <div class="alert alert-danger">
                                                <strong>There were some problems with your input:</strong>
                                                <ul class="mb-0">
                                                    @foreach ($errors->all() as $error)
                                                        <li>{{ $error }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        @if (session('success'))
                                            <div class="alert alert-success">
                                                {{ session('success') }}
                                            </div>
                                        @endif

                                        <div class="mb-3">
                                            <label for="system_name" class="form-label fw-semibold">System Name</label>
                                            <input type="text"
                                                class="form-control form-control-lg @error('system_name') is-invalid @enderror"
                                                name="system_name" id="system_name"
                                                value="{{ old('system_name', $settings->system_name ?? '') }}" required>
                                            @error('system_name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="mb-3">
                                            <label for="system_logo" class="form-label fw-semibold">System Logo</label>
                                            <input type="file" class="form-control @error('logo') is-invalid @enderror"
                                                name="logo" id="system_logo" accept="image/*">
                                            @error('logo')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="mb-3">
                                            <label for="currency" class="form-label fw-semibold">Currency</label>
                                            <select name="currency" id="currency"
                                                class="form-select form-select-lg @error('currency') is-invalid @enderror"
                                                required>
                                                <option value="USD"
                                                    {{ old('currency', $settings->currency) == 'USD' ? 'selected' : '' }}>
                                                    USD</option>
                                                <option value="PKR"
                                                    {{ old('currency', $settings->currency) == 'PKR' ? 'selected' : '' }}>
                                                    PKR</option>
                                                <option value="SAR"
                                                    {{ old('currency', $settings->currency) == 'SAR' ? 'selected' : '' }}>
                                                    SAR</option>
                                            </select>
                                            @error('currency')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="mb-3">
                                            <label for="payment_method" class="form-label fw-semibold">Default Payment
                                                Method</label>
                                            <select name="payment_method" id="payment_method"
                                                class="form-select form-select-lg @error('payment_method') is-invalid @enderror"
                                                required>
                                                <option value="applepay"
                                                    {{ old('payment_method', $settings->payment_method) == 'applepay' ? 'selected' : '' }}>
                                                    Apple Pay</option>
                                                <option value="gpay"
                                                    {{ old('payment_method', $settings->payment_method) == 'gpay' ? 'selected' : '' }}>
                                                    Gpay</option>
                                                <option value="sdc"
                                                    {{ old('payment_method', $settings->payment_method) == 'sdc' ? 'selected' : '' }}>
                                                    SDC</option>
                                            </select>
                                            @error('payment_method')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="d-grid col-4 mx-auto">
                                            <button type="submit" class="btn btn-primary btn-lg">Update Settings</button>
                                        </div>
                                    </form>
                                </div>


                                {{-- Preview Section (Right) --}}
                                {{-- Preview Section (Right) --}}
                                <div class="col-md-5 ps-4">
                                    <div class=" rounded p-4  h-100">


                                        {{-- Logo Preview --}}
                                        <div class="mb-4 text-center">
                                            <label class="form-label text-muted d-block mb-2">System Logo</label>
                                            <img id="logoPreview"
                                                src="{{ !empty($settings->logo) ? asset('uploads/system_settings/' . $settings->logo) : 'https://via.placeholder.com/150x160?text=Logo' }}"
                                                class="img-fluid border rounded bg-white p-2 " style="max-height: 160px;">
                                        </div>

                                        {{-- System Name --}}
                                        <div class="mb-3">
                                            <label class="form-label text-muted mb-1">System Name</label>
                                            <div class="border rounded p-2 bg-white shadow-sm">
                                                <strong
                                                    id="previewSystemName">{{ $settings->system_name ?? 'N/A' }}</strong>
                                            </div>
                                        </div>

                                        {{-- Currency --}}
                                        <div class="mb-3">
                                            <label class="form-label text-muted mb-1">Currency</label>
                                            <div class="border rounded p-2 bg-white shadow-sm">
                                                <strong id="previewCurrency">{{ $settings->currency ?? 'N/A' }}</strong>
                                            </div>
                                        </div>

                                        {{-- Payment Method --}}
                                        <div class="mb-3">
                                            <label class="form-label text-muted mb-1">Payment Method</label>
                                            <div class="border rounded p-2 bg-white shadow-sm">
                                                <strong
                                                    id="previewPaymentMethod">{{ ucfirst($settings->payment_method ?? 'N/A') }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div> <!-- row -->
                        </div> <!-- card-body -->
                    </div>
                </div>


                <div class="col-lg-12 mt-5">
                    <div class="card shadow border-0">
                        <div class="card-header bg-white border-bottom-0">
                            <h6 class="mb-0">Mobile App Banners</h6>
                        </div>
                        <div class="card-body pt-0">
                            <form action="{{ route('mobile_banners.update') }}" method="POST"
                                enctype="multipart/form-data">
                                @csrf
                            {{-- Existing Gallery --}}
                            <div class="mb-3 {{ $images->isEmpty() ? 'd-none' : '' }}">
                                <label class="form-label fw-bold">Existing Banners</label>
                                <div class="d-flex flex-wrap gap-3 mb-2">
                                    @foreach ($images as $image)
                                        <div class="position-relative" style="width: 120px; height: 100px;">
                                            <img src="{{ asset('uploads/mobile_banners/' . $image->path) }}"
                                                class="img-thumbnail w-100 h-100"
                                                style="object-fit: cover;">
                                            @if ($image->showMarketplace && $image->marketplace)
                                                <span class="badge bg-primary text-wrap position-absolute bottom-0 start-0 w-100 rounded-0" style="font-size: 8px; opacity: 0.95; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; z-index: 5;">
                                                    {{ $image->marketplace->marketplaceProfile->shop_title ?? $image->marketplace->name }}
                                                </span>
                                            @endif

                                            <a href="{{ route('mobile_banners.delete', $image->id) }}" type="submit"
                                                class="btn btn-sm btn-danger p-0 rounded-circle"
                                                style="width: 24px; height: 24px; position: absolute; top: 0; right: 0; justify-content: center; align-items: center; display: flex; z-index: 10;">
                                                &times;
                                            </a>

                                        </div>
                                    @endforeach
                                </div>
                            </div>


                            {{-- Upload New Images --}}
                            <div class="mb-3">
                                <label class="form-label fw-bold">Upload Images</label>
                                <div id="gallery-dropzone" class="border rounded p-3" style="min-height: 160px;">
                                    <div id="gallery-preview" class="d-flex flex-wrap gap-3"></div>
                                    <input type="file" id="galleryInput" name="banners[]" multiple
                                        accept="image/*" class="d-none">
                                    <div class="text-center mt-2">
                                        <button type="button" id="uploadGalleryBtn" class="btn btn-light">
                                            <i class="bi bi-image me-1"></i> Select Images
                                        </button>
                                    </div>
                                </div>
                                @error('banners')
                                    <small class="text-danger d-block mt-1">{{ $message }}</small>
                                @enderror
                                @error('banners.*')
                                    <small class="text-danger d-block mt-1">{{ $message }}</small>
                                @enderror
                            </div>

                            {{-- showMarketplace Toggle and Marketplace Selection --}}
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="showMarketplace" id="showMarketplace" value="1"
                                        {{ old('showMarketplace') ? 'checked' : '' }}>
                                    <label class="form-check-label fw-bold" for="showMarketplace">Link Banner with Marketplace</label>
                                </div>
                            </div>

                            <div class="mb-3 {{ old('showMarketplace') ? '' : 'd-none' }}" id="marketplace-select-container">
                                <label for="marketplace_id" class="form-label fw-bold">Select Marketplace</label>
                                <select name="marketplace_id" id="marketplace_id" class="form-select @error('marketplace_id') is-invalid @enderror"
                                    {{ old('showMarketplace') ? 'required' : '' }}>
                                    <option value="">-- Choose Marketplace --</option>
                                    @foreach($marketplaces as $marketplace)
                                        <option value="{{ $marketplace->id }}" {{ old('marketplace_id') == $marketplace->id ? 'selected' : '' }}>
                                            {{ $marketplace->marketplaceProfile->shop_title ?? $marketplace->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('marketplace_id')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3 text-end">
                                <button type="submit" class="btn btn-primary">Update Banners</button>
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
        document.getElementById('system_logo').addEventListener('change', function(e) {
            const [file] = e.target.files;
            if (file) {
                document.getElementById('logoPreview').src = URL.createObjectURL(file);
            }
        });

        // Optional live text preview
        document.getElementById('system_name').addEventListener('input', function() {
            document.getElementById('previewSystemName').innerText = this.value || 'N/A';
        });

        document.getElementById('currency').addEventListener('change', function() {
            document.getElementById('previewCurrency').innerText = this.value;
        });

        document.getElementById('payment_method').addEventListener('change', function() {
            document.getElementById('previewPaymentMethod').innerText = this.value.charAt(0).toUpperCase() + this
                .value.slice(1);
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

    <script src="{{ asset('customjs/system_settings/index.js') }}"></script>
@endpush
