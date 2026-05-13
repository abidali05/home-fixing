@extends('layouts.app')

@section('title', 'Edit Provider')

<link rel="stylesheet" href="{{ asset('assets/css/admin/providers/create.css') }}">

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-md-10 col-lg-12">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Edit Provider</h6>
                            <a href="{{ route('providers.index') }}" class="btn btn-sm btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                        </div>
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="card-body">
                            @if (session('success'))
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    {{ session('success') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"
                                        aria-label="Close"></button>
                                </div>
                            @endif

                            @if (session('error'))
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    {{ session('error') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"
                                        aria-label="Close"></button>
                                </div>
                            @endif

                            <form action="{{ route('providers.update', $provider->id) }}" method="POST"
                                enctype="multipart/form-data">
                                @csrf
                                @method('POST')

                                {{-- Profile Image --}}
                                <div class="mb-4 text-end me-2">
                                    <label for="profile" class="d-inline-block">
                                        <img id="profilePreview"
                                            src="{{ $provider->profile_image ? asset('uploads/profile_images/' . $provider->profile_image) : asset('assets/img/default.jpg') }}"
                                            class="rounded shadow"
                                            style="width: 200px; height: 150px; object-fit: contain; cursor: pointer;">
                                    </label>
                                    <input type="file" name="profile_image" id="profile" accept="image/*"
                                        class="d-none">
                                    @error('profile_image')
                                        <small class="text-danger d-block mt-2">{{ $message }}</small>
                                    @enderror
                                </div>

                                {{-- Name, Phone, Service Type --}}
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="name" class="form-label">Full Name</label>
                                        <input type="text" name="name" id="name" class="form-control"
                                            value="{{ old('name', $provider->name) }}">
                                        @error('name')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="text" name="phone" id="phone" class="form-control"
                                            value="{{ old('phone', $provider->phone) }}">
                                        @error('phone')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label for="service_category" class="form-label">Service Categories</label>
                                        <select name="service_category[]" id="service_category" class="form-select select2"
                                            multiple>
                                            @php
                                                $selectedCategories = is_array($provider->service_category)
                                                    ? $provider->service_category
                                                    : json_decode($provider->service_category, true) ?? [];
                                            @endphp

                                            @foreach ($services as $service)
                                                <option value="{{ $service->id }}"
                                                    {{ in_array($service->id, old('service_category', $selectedCategories)) ? 'selected' : '' }}>
                                                    {{ $service->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('service_category')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                </div>

                                {{-- Address & Experience --}}
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <input type="text" name="address" id="address" class="form-control"
                                            value="{{ old('address', $provider->address) }}">
                                        @error('address')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    {{-- <div class="col-md-6 mb-3">
                                        <label for="experience" class="form-label">Experience</label>
                                        <select name="experience" id="experience" class="form-select">
                                            <option value="">Select experience</option>
                                            @foreach (['1-3 years', '3-5 years', '5-10 years', '10+ years'] as $exp)
                                                <option value="{{ $exp }}"
                                                    {{ old('experience', $provider->experience) == $exp ? 'selected' : '' }}>
                                                    {{ $exp }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('experience')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div> --}}

                                    <div class="col-md-4 mb-3">
                                        <label for="dob" class="form-label">Date of Birth</label>
                                        <input type="date" name="dob" id="dob" class="form-control"
                                            value="{{ old('dob', $provider->dob) }}" max="{{ date('Y-m-d') }}">
                                        @error('dob')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label for="price_amount" class="form-label">Status</label>
                                        <select name="status" id="status" class="form-select">
                                            <option value="active"
                                                {{ old('status', $provider->status) == 'active' ? 'selected' : '' }}>
                                                Active</option>
                                            <option value="inactive"
                                                {{ old('status', $provider->status) == 'inactive' ? 'selected' : '' }}>
                                                Inactive</option>
                                            <option value="suspended"
                                                {{ old('status', $provider->status) == 'suspended' ? 'selected' : '' }}>
                                                Suspended</option>
                                            <option value="banned"
                                                {{ old('status', $provider->status) == 'banned' ? 'selected' : '' }}>
                                                Banned</option>
                                        </select>
                                        @error('status')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                </div>

                                {{-- Working Hours --}}
                                {{-- <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="dob" class="form-label">Date of Birth</label>
                                        <input type="date" name="dob" id="dob" class="form-control"
                                            value="{{ old('dob', $provider->dob) }}" max="{{ date('Y-m-d') }}">
                                        @error('dob')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    @php
                                        $fromTime = $provider->work_hour_start
                                            ? \Carbon\Carbon::parse($provider->work_hour_start)->format('H:i')
                                            : '';
                                        $toTime = $provider->work_hour_end
                                            ? \Carbon\Carbon::parse($provider->work_hour_end)->format('H:i')
                                            : '';
                                    @endphp

                                    <div class="col-md-4 mb-3">
                                        <label for="from_time" class="form-label">Working From</label>
                                        <input type="time" name="from_time" id="from_time" class="form-control"
                                            value="{{ old('from_time', $fromTime) }}">

                                        @error('from_time')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label for="to_time" class="form-label">Working To</label>
                                        <input type="time" name="to_time" id="to_time" class="form-control"
                                            value="{{ old('to_time', $toTime) }}">
                                        @error('to_time')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div> --}}

                                {{-- License and Certification --}}
                                {{-- License and Certification --}}
                                <div class="row">
                                    {{-- <div class="col-md-6 mb-3">
                                        <label for="license_file" class="form-label">Service License</label>
                                        <input type="file" name="license_file" id="license_file"
                                            class="form-control">
                                        @if ($provider->service_license)
                                            <div class="mt-2">
                                                @php
                                                    $licenseExt = strtolower(
                                                        pathinfo($provider->service_license, PATHINFO_EXTENSION),
                                                    );
                                                    $isImage = in_array($licenseExt, [
                                                        'jpg',
                                                        'jpeg',
                                                        'png',
                                                        'gif',
                                                        'webp',
                                                    ]);
                                                @endphp

                                                @if ($isImage)
                                                    <img src="{{ asset('storage/uploads/licenses/' . $provider->service_license) }}"
                                                        alt="Service License" class="rounded border"
                                                        style="width: 150px; height: 120px; object-fit: cover;">
                                                @else
                                                    <a href="{{ asset('storage/uploads/licenses/' . $provider->service_license) }}"
                                                        target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                                                        <i class="bi bi-file-earmark-arrow-down"></i> Download License
                                                    </a>
                                                @endif
                                            </div>
                                        @endif
                                        @error('license_file')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div> --}}

                                    <div class="col-md-6 mb-3">
                                        <label for="document_type" class="form-label">Document Type</label>
                                        <select name="document_type" id="document_type" class="form-select">
                                            <option value="">Select Document Type</option>
                                            <option value="id_number"
                                                {{ old('document_type', $provider->document_type) == 'id_number' ? 'selected' : '' }}>
                                                ID number</option>
                                            <option value="cr_number"
                                                {{ old('document_type', $provider->document_type) == 'cr_number' ? 'selected' : '' }}>
                                                CR number</option>
                                            <option value="iqama_number"
                                                {{ old('document_type', $provider->document_type) == 'iqama_number' ? 'selected' : '' }}>
                                                Iqama number</option>
                                            <option value="passport_number"
                                                {{ old('document_type', $provider->document_type) == 'passport_number' ? 'selected' : '' }}>
                                                Passport number</option>
                                        </select>
                                        @error('document_type')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>


                                    <div class="col-md-6 mb-3">
                                        <label for="document_number" class="form-label">Document Number</label>
                                        <input type="text" name="document_number" id="document_number"
                                            class="form-control"
                                            value="{{ old('document_number', $provider->document_number) }}">
                                        @error('document_number')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    {{-- <div class="col-md-6 mb-3">
                                        <label for="certification_file" class="form-label">Certification</label>
                                        <input type="file" name="certification_file" id="certification_file"
                                            class="form-control">
                                        @if ($provider->certification)
                                            <div class="mt-2">
                                                @php
                                                    $certExt = strtolower(
                                                        pathinfo($provider->certification, PATHINFO_EXTENSION),
                                                    );
                                                    $isCertImage = in_array($certExt, [
                                                        'jpg',
                                                        'jpeg',
                                                        'png',
                                                        'gif',
                                                        'webp',
                                                    ]);
                                                @endphp

                                                @if ($isCertImage)
                                                    <img src="{{ asset('storage/uploads/certifications/' . $provider->certification) }}"
                                                        alt="Certification" class="rounded border"
                                                        style="width: 150px; height: 120px; object-fit: cover;">
                                                @else
                                                    <a href="{{ asset('storage/uploads/certifications/' . $provider->certification) }}"
                                                        target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                                                        <i class="bi bi-file-earmark-arrow-down"></i> Download
                                                        Certification
                                                    </a>
                                                @endif
                                            </div>
                                        @endif
                                        @error('certification_file')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div> --}}
                                </div>


                                {{-- Gallery Dropzone --}}
                                {{-- <div class="mb-3">
                                    <label class="form-label fw-bold">Upload Gallery</label>
                                    <div id="gallery-dropzone" class="border rounded p-3" style="min-height: 160px;">
                                        <div id="gallery-preview" class="d-flex flex-wrap gap-3">
                                            @foreach ($gallery as $image)
                                                <div class="preview-wrapper position-relative">
                                                    <img src="{{ asset('uploads/provider_gallery/' . $image->path) }}"
                                                        style="width:100px; height:100px; object-fit:cover;"
                                                        class="rounded border">
                                                    <a href="{{ route('providers.deleteProviderImage', $image->id) }}"
                                                        type="button" id="removeProfileBtn"
                                                        class="btn btn-sm btn-danger position-absolute"
                                                        style="top: -8px; right: -8px; border-radius: 50%; padding: 0.25rem 0.45rem; z-index: 10;">
                                                        &times;
                                                    </a>

                                                </div>
                                            @endforeach
                                        </div>
                                        <input type="file" id="galleryInput" name="gallery[]" multiple
                                            accept="image/*" class="d-none">
                                        <div class="text-center mt-2">
                                            <button type="button" id="uploadGalleryBtn" class="btn btn-light">
                                                <i class="bi bi-image me-1"></i> Select Images
                                            </button>
                                        </div>
                                    </div>
                                    @error('gallery')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div> --}}

                                {{-- Bio --}}
                                <div class="mb-3">
                                    <label for="bio" class="form-label">Bio</label>
                                    <textarea name="bio" id="bio" rows="3" class="form-control">{{ old('bio', $provider->bio) }}</textarea>
                                    @error('bio')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                {{-- Pricing --}}
                                {{-- <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="pricing_type" class="form-label">Pricing Type</label>
                                        <select name="pricing_type" id="pricing_type" class="form-select">
                                            <option value="hourly"
                                                {{ old('pricing_type', $provider->charge_type) == 'hourly' ? 'selected' : '' }}>
                                                Hourly</option>
                                            <option value="fixed"
                                                {{ old('pricing_type', $provider->charge_type) == 'fixed' ? 'selected' : '' }}>
                                                Fixed</option>
                                        </select>
                                        @error('pricing_type')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label for="price_amount" class="form-label">Amount (SAR)</label>
                                        <input type="number" name="price_amount" id="price_amount" class="form-control"
                                            value="{{ old('price_amount', $provider->charge_amount) }}">
                                        @error('price_amount')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label for="price_amount" class="form-label">Status</label>
                                        <select name="status" id="status" class="form-select">
                                            <option value="active"
                                                {{ old('status', $provider->status) == 'active' ? 'selected' : '' }}>
                                                Active</option>
                                            <option value="inactive"
                                                {{ old('status', $provider->status) == 'inactive' ? 'selected' : '' }}>
                                                Inactive</option>
                                            <option value="suspended"
                                                {{ old('status', $provider->status) == 'suspended' ? 'selected' : '' }}>
                                                Suspended</option>
                                            <option value="banned"
                                                {{ old('status', $provider->status) == 'banned' ? 'selected' : '' }}>
                                                Banned</option>
                                        </select>
                                        @error('status')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div> --}}

                                {{-- Submit --}}
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary px-4">
                                        <i class="bi bi-save me-1"></i> Update
                                    </button>
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
    <script src="{{ asset('customjs/providers/create.js') }}"></script>
@endpush
