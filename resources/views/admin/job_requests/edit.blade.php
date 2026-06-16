@extends('layouts.app')

@section('title', 'Edit Service Request')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-lg-12">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Edit Service Request</h6>
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
                            <form action="{{ route('job_requests.update', $jobRequest->id) }}" method="POST"
                                enctype="multipart/form-data">
                                @csrf
                                @method('POST')

                                {{-- User & Service --}}
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Select User</label>
                                        <select name="user_id" id="user_id" class="form-select select2" required>
                                            <option value="">Select User</option>
                                            @foreach ($users as $user)
                                                <option value="{{ $user->id }}"
                                                    {{ $jobRequest->user_id == $user->id ? 'selected' : '' }}>
                                                    {{ $user->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('user_id')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Service</label>
                                        <select name="service_id" id="service_id" class="form-select select2" required>
                                            <option value="">Select Your Service</option>
                                            @foreach ($services as $service)
                                                <option value="{{ $service->id }}"
                                                    {{ $jobRequest->category_id == $service->id ? 'selected' : '' }}>
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
                                    <label class="form-label">Provide Specific Instructions or Details</label>
                                    <textarea name="description" id="description" rows="4" class="form-control" required>{{ old('description', $jobRequest->description) }}</textarea>
                                    @error('description')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                {{-- Date & Time --}}
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Date</label>
                                        <input type="date" name="date" id="date" class="form-control"
                                            value="{{ old('date', $jobRequest->job_date) }}" required>
                                        @error('date')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Time</label>
                                        <input type="time" name="time" id="time" class="form-control"
                                            value="{{ old('time', $jobRequest->job_time) }}" required>
                                        @error('time')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>

                                {{-- Price --}}
                                <div class="mb-3">
                                    <label class="form-label">Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text">SAR</span>
                                        <input type="number" name="price" id="price" class="form-control"
                                            value="{{ old('price', $jobRequest->price) }}" required>
                                    </div>
                                    @error('price')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                {{-- Address --}}
                                <div class="mb-3">
                                    <label class="form-label">Complete Address</label>
                                    <input type="text" name="address" id="address" class="form-control"
                                        value="{{ old('address', $jobRequest->address) }}" required>
                                    @error('address')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                {{-- Existing Gallery --}}
                                <div class="mb-3 {{ $images->isEmpty() ? 'd-none' : '' }}">
                                    <label class="form-label fw-bold">Existing Gallery</label>
                                    <div class="d-flex flex-wrap gap-3 mb-2">
                                        @foreach ($images as $image)
                                            <div class="position-relative" style="width: 120px;">
                                                <img src="{{ asset('uploads/job_gallery/' . $image->path) }}"
                                                    class="img-thumbnail"
                                                    style="width: 120px; height: 100px; object-fit: cover;">


                                                <a href="{{ route('job_requests.deleteJobImage', $image->id) }}" type="submit" class="btn btn-sm btn-danger p-0 rounded-circle"
                                                    style="width: 24px; height: 24px; position: absolute; top: 0; right: 0; justify-content: center; align-items: center;">
                                                    &times;
                                                </a>

                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                {{-- Existing Video --}}
                                @if ($jobRequest->video)
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Existing Video</label>
                                        <div class="position-relative" style="width: 200px;">
                                            <video controls class="img-thumbnail" style="width: 200px; height: 150px; object-fit: cover;">
                                                <source src="{{ $jobRequest->video }}" type="video/mp4">
                                                Your browser does not support the video tag.
                                            </video>
                                            <a href="{{ route('job_requests.deleteJobVideo', $jobRequest->id) }}" class="btn btn-sm btn-danger p-0 rounded-circle"
                                                style="width: 24px; height: 24px; position: absolute; top: 0; right: 0; justify-content: center; align-items: center; display: flex;">
                                                &times;
                                            </a>
                                        </div>
                                    </div>
                                @endif


                                {{-- Upload New Images --}}
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Upload Images</label>
                                    <div id="gallery-dropzone" class="border rounded p-3" style="min-height: 160px;">
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
                                    <button type="submit" style="background-color: #2BBDCE; color: white;" class="btn ">
                                        <i class="bi bi-save me-1"></i> Update Request
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
