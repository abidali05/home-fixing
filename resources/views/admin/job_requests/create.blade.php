@extends('layouts.app')

@section('title', 'Create Service Request')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-lg-12">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Create Service Post</h6>
                            <a href="{{ route('job_requests.index') }}" class="btn btn-sm btn-secondary">
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
                            <form action="{{ route('job_requests.store') }}" id="serviceRequestForm" method="POST" enctype="multipart/form-data">
                                @csrf

                                {{-- Service --}}
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="first_name" class="form-label">Select User</label>
                                        <select name="user_id" id="user_id" class="form-select select2" >
                                            <option value="">Select User</option>
                                            @foreach ($users as $user)
                                                <option value="{{ $user->id }}"
                                                    {{ old('user_id') == $user->id ? 'selected' : '' }}>
                                                    {{ $user->name . ' (' . $user->phone . ')' }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('user_id')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="service_id" class="form-label">Service</label>
                                        <select name="service_id" id="service_id" class="form-select select2" >
                                            <option value="">Select Your Service</option>
                                            @foreach ($services as $service)
                                                <option value="{{ $service->id }}"
                                                    {{ old('service_id') == $service->id ? 'selected' : '' }}>
                                                    {{ $service->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('service_id')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                </div>

                                {{-- Instructions --}}
                                <div class="mb-3">
                                    <label for="instructions" class="form-label">Provide Specific Instructions or
                                        Details</label>
                                    <textarea name="instructions" id="instructions" rows="4" class="form-control" placeholder="Write here..."
                                        >{{ old('instructions') }}</textarea>
                                    @error('instructions')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                {{-- Date & Time --}}
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="date" class="form-label">Date</label>
                                        <input type="date" name="date" id="date" class="form-control"
                                            value="{{ old('date') }}" >
                                        @error('date')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label for="time" class="form-label">Time</label>
                                        <input type="time" name="time" id="time" class="form-control"
                                            value="{{ old('time') }}" >
                                        @error('time')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>

                                {{-- Price --}}
                                <div class="mb-3">
                                    <label for="price" class="form-label">Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text">SAR</span>
                                        <input type="number" name="price" id="price" class="form-control"
                                            placeholder="1234" value="{{ old('price') }}" >
                                    </div>
                                    @error('price')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                {{-- Address --}}
                                <div class="mb-3">
                                    <label for="address" class="form-label">Complete Address</label>
                                    <input type="text" name="address" id="address" class="form-control"
                                        placeholder="Enter Address" value="{{ old('address') }}" >
                                    @error('address')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Upload Gallery</label>
                                    <div id="gallery-dropzone" class="border rounded p-3 "
                                        style="min-height: 160px;">
                                        <div id="gallery-preview" class="d-flex flex-wrap gap-3"></div>
                                        <input type="file" id="galleryInput" name="place_pictures[]" multiple
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

                                {{-- Submit --}}
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn " style="background-color: #2BBDCE; color: white;">
                                        <i class="bi bi-upload me-1"></i> Submit Request
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    @push('scripts')
        <script src="{{ asset('customjs/job_requests/create.js') }}"></script>
    @endpush
@endsection
