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
                                         <div class="col-md-6"><strong>Phone:</strong> {{ $provider->phone }}</div>
                                         <div class="col-md-6"><strong>Status:</strong> {{ ucfirst($provider->provider_status ?: 'inactive') }}</div>
                                         <div class="col-md-6"><strong>Provider Type:</strong> {{ ucfirst($profile?->provider_type ?: 'individual') }}</div>
                                         <div class="col-md-6"><strong>Company:</strong> {{ $profile?->company_name ?: '-' }}</div>
                                         <div class="col-md-6"><strong>Experience:</strong> {{ $profile?->experience ?: '-' }}</div>
                                         <div class="col-md-6">
                                             <strong>Referral Code:</strong> 
                                             <span class="badge text-white px-2 py-1 ms-1" style="background-color: #4F2396; font-size: 0.85rem;">
                                                 <i class="bi bi-ticket-perforated me-1"></i>{{ $profile?->referral_code ?: 'N/A' }}
                                             </span>
                                         </div>
                                         <div class="col-md-6">
                                              <strong>Referred By:</strong>
                                              @php
                                                  $refData = $profile?->referred_by ?: $profile?->referredBy;
                                                  $refName = is_array($refData) ? ($refData['name'] ?? null) : ($refData?->name ?? null);
                                                  $refId = is_array($refData) ? ($refData['id'] ?? null) : ($refData?->id ?? null);
                                              @endphp
                                              @if($refName)
                                                  <a href="{{ $refId ? route('providers.show', $refId) : '#' }}" class="badge bg-light text-primary border text-decoration-none ms-1">
                                                      <i class="bi bi-person-check text-success me-1"></i>{{ $refName }} ({{ $profile->referred_by_code }})
                                                  </a>
                                              @else
                                                  <span class="badge bg-light text-secondary border ms-1">Direct Registration (No Referrer)</span>
                                              @endif
                                         </div>
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

                            <!-- Referred Providers Section -->
                            <div class="mb-4">
                                <h6 class="mb-3 d-flex align-items-center">
                                    <i class="bi bi-people-fill text-primary me-2"></i>Referred Providers Network
                                    <span class="badge rounded-pill bg-primary ms-2">{{ count($referredProviders ?? []) }}</span>
                                </h6>
                                @if(isset($referredProviders) && count($referredProviders) > 0)
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Provider Name</th>
                                                    <th>Phone</th>
                                                    <th>User Code</th>
                                                    <th>Status</th>
                                                    <th>Joined Date</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($referredProviders as $refProv)
                                                    <tr>
                                                        <td>#{{ $refProv->id }}</td>
                                                        <td class="fw-semibold">{{ $refProv->name }}</td>
                                                        <td>{{ $refProv->phone }}</td>
                                                        <td><code>{{ $refProv->user_code ?: '-' }}</code></td>
                                                        <td>
                                                            <span class="badge bg-{{ $refProv->provider_status === 'active' ? 'success' : 'secondary' }}">
                                                                {{ ucfirst($refProv->provider_status ?: 'inactive') }}
                                                            </span>
                                                        </td>
                                                        <td>{{ optional($refProv->created_at)->format('d M Y, h:i A') }}</td>
                                                        <td>
                                                            <a href="{{ route('providers.show', $refProv->id) }}" class="btn btn-xs btn-outline-primary py-0 px-2">
                                                                <i class="bi bi-eye"></i> View
                                                            </a>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="alert alert-light border text-muted py-2 px-3 small">
                                        <i class="bi bi-info-circle me-1"></i> No providers have registered using this provider's referral code yet.
                                    </div>
                                @endif
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
