@extends('layouts.app')

@section('title', 'Provider Details')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            @php
                $profile = $provider->providerProfile;
                $serviceIds = is_array($profile?->service_category) ? $profile?->service_category : [];
                $services = \App\Models\Admin\ServiceCategoryModel::whereIn('id', $serviceIds)->pluck('name')->toArray();
            @endphp
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Provider Details</h6>
                            <div class="d-flex gap-2">
                                <a href="{{ route('providers.edit', $provider->id) }}" class="btn btn-sm btn-primary">Edit</a>
                                <a href="{{ route('providers.index') }}" class="btn btn-sm btn-secondary">Back</a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <img src="{{ $provider->profile_image ? asset('uploads/profile_images/' . $provider->profile_image) : asset('assets/img/default.jpg') }}"
                                        class="img-fluid rounded border" alt="{{ $provider->name }}">
                                </div>
                                <div class="col-md-8">
                                    <div class="row g-3">
                                        <div class="col-md-6"><strong>Name:</strong> {{ $provider->name }}</div>
                                        <div class="col-md-6"><strong>Email:</strong> {{ $provider->email ?: '-' }}</div>
                                        <div class="col-md-6"><strong>Phone:</strong> {{ $provider->phone }}</div>
                                        <div class="col-md-6"><strong>City:</strong> {{ $provider->cityname }}</div>
                                        <div class="col-md-6"><strong>Status:</strong> {{ ucfirst($provider->provider_status ?: 'inactive') }}</div>
                                        <div class="col-md-6"><strong>Provider Type:</strong> {{ ucfirst($profile?->provider_type ?: 'individual') }}</div>
                                        <div class="col-md-6"><strong>Company:</strong> {{ $profile?->company_name ?: '-' }}</div>
                                        <div class="col-md-6"><strong>Experience:</strong> {{ $profile?->experience ?: '-' }}</div>
                                        <div class="col-md-6"><strong>Charge Type:</strong> {{ $profile?->charge_type ?: '-' }}</div>
                                        <div class="col-md-6"><strong>Charge Amount:</strong> {{ $profile?->charge_amount ?: '-' }}</div>
                                        <div class="col-md-12"><strong>Address:</strong> {{ $profile?->address ?: '-' }}</div>
                                        <div class="col-md-12"><strong>Services:</strong>
                                            @forelse ($services as $service)
                                                <span class="badge bg-light text-dark border me-1">{{ $service }}</span>
                                            @empty
                                                <span class="text-muted">No services selected</span>
                                            @endforelse
                                        </div>
                                        <div class="col-md-12"><strong>Bio:</strong> {{ $profile?->bio ?: '-' }}</div>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <h6>Gallery</h6>
                            <div class="row g-3">
                                @forelse ($gallery as $image)
                                    <div class="col-md-3">
                                        <img src="{{ asset('uploads/provider_gallery/' . $image->path) }}" class="img-fluid rounded border" alt="Gallery image">
                                    </div>
                                @empty
                                    <div class="col-12 text-muted">No gallery images found.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
