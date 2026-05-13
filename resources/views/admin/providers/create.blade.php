@extends('layouts.app')

@section('title', 'Add New Provider')


<link rel="stylesheet" href="{{ asset('assets/css/admin/providers/create.css') }}">


@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-md-10 col-lg-12">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Add New Provider</h6>
                            <a href="{{ route('providers.index') }}" class="btn btn-sm btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                        </div>

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
                            <form action="{{ route('providers.store') }}" id="providerForm" method="POST"
                                enctype="multipart/form-data">
                                @csrf

                                {{-- Profile Image --}}
                                <div class="mb-4 text-end me-2">
                                    <label for="profile" class="d-inline-block">
                                        <img id="profilePreview" src="{{ asset('assets/img/default.jpg') }}"
                                            class="rounded shadow"
                                            style="width: 200px; height: 150px; object-fit: contain; cursor: pointer;">
                                    </label>
                                    <input type="file" name="profile_image" id="profile" accept="image/*"
                                        class="d-none">
                                    @error('profile_image')
                                        <small class="text-danger d-block mt-2">{{ $message }}</small>
                                    @enderror
                                </div>

                                {{-- Name, Phone, City --}}
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="name" class="form-label">Full Name</label>
                                        <input type="text" name="name" id="name" class="form-control"
                                            value="{{ old('name') }}">
                                        @error('name')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="text" name="phone" id="phone" class="form-control"
                                            value="{{ old('phone') }}" placeholder="+966 5xxxxxxx">
                                        @error('phone')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    {{-- <div class="col-md-4 mb-3 ">
                                        <label for="city" class="form-label">City</label>
                                        <select name="city" id="city" class="form-select select2">
                                            <option value="">Select city</option>
                                            @foreach ($cities as $city)
                                                <option value="{{ $city->id }}"
                                                    {{ old('city') == $city->id ? 'selected' : '' }}>
                                                    {{ $city->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('city')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div> --}}

                                    <div class="col-md-4 mb-3">
                                        <label for="service_category" class="form-label">Service Type</label>
                                        <select name="service_category[]" id="service_category" class="form-select select2" multiple>
                                            <option value="">Select your service</option>
                                            @foreach ($services as $service)
                                                <option value="{{ $service->id }}"
                                                    {{ old('service_category') == $service->id ? 'selected' : '' }}>
                                                    {{ $service->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('service_category')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>

                                {{-- Address, Service Type, Experience --}}
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <input type="text" name="address" id="address" class="form-control"
                                            value="{{ old('address') }}">
                                        @error('address')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>



                                    <div class="col-md-6 mb-3">
                                        <label for="experience" class="form-label">Experience</label>


                                        <select name="experience" id="experience" class="form-select select2">
                                            <option value="">Select your experience

                                            <option value="1-3 years"
                                                {{ old('experience') == '1-3 years' ? 'selected' : '' }}>
                                                1-3 years</option>
                                            <option value="3-5 years"
                                                {{ old('experience') == '3-5 years' ? 'selected' : '' }}>
                                                3-5 years</option>
                                            <option value="5-10 years"
                                                {{ old('experience') == '5-10 years' ? 'selected' : '' }}>5-10 years
                                            </option>
                                            <option value="10+ years"
                                                {{ old('experience') == '10+ years' ? 'selected' : '' }}>
                                                10+ years</option>
                                        </select>

                                        @error('experience')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>

                                {{-- Working Hours --}}
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="dob" class="form-label">Date of Birth</label>
                                        <input type="date" name="dob" id="dob" class="form-control"
                                            value="{{ old('dob') }}" max="{{ date('Y-m-d') }}">
                                        @error('dob')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label for="from_time" class="form-label">Working From</label>
                                        <input type="time" name="from_time" id="from_time" class="form-control"
                                            value="{{ old('from_time') }}">
                                        @error('from_time')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label for="to_time" class="form-label">Working To</label>
                                        <input type="time" name="to_time" id="to_time" class="form-control"
                                            value="{{ old('to_time') }}">
                                        @error('to_time')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>

                                {{-- License and Certification --}}
                                <div class="row">
                                    {{-- <div class="col-md-6 mb-3">
                                        <label for="license_file" class="form-label">Service License</label>
                                        <input type="file" name="license_file" id="license_file" class="form-control"
                                        >
                                        @error('license_file')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div> --}}

                                    <div class="col-md-6 mb-3">
                                        <label for="license_file" class="form-label">Service License</label>
                                        <input type="text" name="license_file" id="license_file" class="form-control"
                                            value="{{ old('license_file') }}">
                                        @error('license_file')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="certification_file" class="form-label">Certification</label>
                                        <input type="file" name="certification_file" id="certification_file"
                                            class="form-control">
                                        @error('certification_file')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>

                                {{-- Gallery Dropzone --}}
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Upload Gallery</label>
                                    <div id="gallery-dropzone" class="border rounded p-3 " style="min-height: 160px;">
                                        <div id="gallery-preview" class="d-flex flex-wrap gap-3"></div>
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
                                </div>

                                {{-- Bio --}}
                                <div class="mb-3">
                                    <label for="bio" class="form-label">Bio</label>
                                    <textarea name="bio" id="bio" rows="3" class="form-control">{{ old('bio') }}</textarea>
                                    @error('bio')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                {{-- Pricing --}}
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="pricing_type" class="form-label">Pricing Type</label>
                                        <select name="pricing_type" id="pricing_type" class="form-select">
                                            <option value="hourly"
                                                {{ old('pricing_type') == 'hourly' ? 'selected' : '' }}>Hourly</option>
                                            <option value="fixed" {{ old('pricing_type') == 'fixed' ? 'selected' : '' }}>
                                                Fixed</option>
                                        </select>
                                        @error('pricing_type')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="price_amount" class="form-label">Amount (SAR)</label>
                                        <input type="number" name="price_amount" id="price_amount" class="form-control"
                                            value="{{ old('price_amount') }}">
                                        @error('price_amount')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>

                                {{-- Submit --}}
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary px-4">
                                        <i class="bi bi-save me-1"></i> Submit
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
