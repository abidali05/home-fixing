@extends('layouts.app')

@section('title', 'Service Request Details')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-lg-12">
                    {{-- Service Request Details --}}
                    <div class="card shadow-sm mb-4">
                        <div class="card-header text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Service Request Details</h5>
                            <a href="{{ route('job_requests.index') }}" class="btn btn-light btn-sm">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <strong>User:</strong> {{ $jobRequest->user->name }}
                                </div>
                                <div class="col-md-6">
                                    <strong>Service:</strong> {{ $jobRequest->category->name }}
                                </div>
                                <div class="col-md-6">
                                    <strong>Requested Date:</strong>
                                    {{ \Carbon\Carbon::parse($jobRequest->date)->format('d M, Y') }}
                                </div>
                                <div class="col-md-6">
                                    <strong>Requested Time:</strong>
                                    {{ \Carbon\Carbon::parse($jobRequest->time)->format('h:i A') }}
                                </div>
                                <div class="col-md-6">
                                    <strong>Price:</strong> SAR {{ number_format($jobRequest->price, 2) }}
                                </div>
                                <div class="col-md-6">
                                    <strong>Address:</strong> {{ $jobRequest->address }}
                                </div>
                                <div class="col-md-6">
                                    <strong>Submitted Date:</strong>
                                    {{ \Carbon\Carbon::parse($jobRequest->created_at)->format('d F, Y') }}
                                </div>
                                <div class="col-md-6">
                                    <strong>Status:</strong>
                                    <span
                                        class="badge bg-{{ $jobRequest->status == 'pending' ? 'warning' : ($jobRequest->status == 'completed' ? 'success' : 'secondary') }}">
                                        {{ ucfirst($jobRequest->status) }}
                                    </span>
                                </div>
                                <div class="col-12">
                                    <strong>Description:</strong>
                                    <p class="mt-1">{{ $jobRequest->description }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Gallery --}}
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0 fw-bold">Gallery</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-3">
                                @forelse($images as $image)
                                    <img src="{{ asset('uploads/job_gallery/' . $image->path) }}"
                                        class="img-thumbnail border" style="width: 150px; height: 120px; object-fit: cover;"
                                        alt="Gallery Image">
                                @empty
                                    <p class="text-muted">No images uploaded.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    {{-- Video --}}
                    @if ($jobRequest->video)
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light">
                                <h6 class="mb-0 fw-bold">Video Attachment</h6>
                            </div>
                            <div class="card-body">
                                <video controls class="img-thumbnail border" style="max-width: 100%; max-height: 300px;">
                                    <source src="{{ $jobRequest->video }}" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        </div>
                    @endif

                    {{-- Accepted Bid --}}
                    @if ($bid)
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light">
                                <h6 class="mb-0 fw-bold">Accepted Bid</h6>
                            </div>
                            <div class="card-body">
                                {{-- <div class="row g-3"> --}}
                                    <div class="col-md-6 mt-2">
                                        <strong>Provider Image:</strong><br>
                                        <img src="{{ $bid->provider->profile_image ? asset('uploads/profile_images/' . $bid->provider->profile_image) : asset('assets/img/default.jpg') }}"
                                            class="img-thumbnail mt-2"
                                            style="width: 150px; height: 120px; object-fit: cover;" alt="">
                                    </div>
                                    <div class="col-md-6 mt-2">
                                        <strong>Provider Name:</strong> {{ $bid->provider->name }}
                                    </div>
                                    <div class="col-md-6 mt-2">
                                        <strong>Bid Price:</strong> SAR {{ number_format($bid->price, 2) }}
                                    </div>
                                    <div class="col-md-6 mt-2">
                                        <strong>Time:</strong> {{ $bid->bid_time }}
                                    </div>
                                    <div class="col-12 mt-2">
                                        <strong>Description:</strong>
                                        <p class="">{{ $bid->bid_details }}</p>
                                    </div>
                                {{-- </div> --}}
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </main>
@endsection
