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
                            @if (session('success'))
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

                            @if (session('error'))
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="bi bi-exclamation-triangle me-1"></i> {{ session('error') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

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

                            <!-- Identity Verification (KYC) Section -->
                            <div class="card mb-4 border shadow-sm">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0 fw-bold text-dark">
                                            <i class="bi bi-shield-check text-primary me-2"></i>Provider Identity Verification (KYC)
                                        </h6>
                                        <small class="text-muted">Compare provider's government identity document against the live camera selfie.</small>
                                    </div>
                                    <div>
                                        @php
                                            $kycStatus = $provider->verification_status ?: ($profile?->verification_status ?: 'pending');
                                        @endphp
                                        @if($kycStatus === 'approved')
                                            <span class="badge bg-success px-3 py-2 fs-7"><i class="bi bi-check-circle me-1"></i> KYC Approved</span>
                                        @elseif($kycStatus === 'rejected')
                                            <span class="badge bg-danger px-3 py-2 fs-7"><i class="bi bi-x-circle me-1"></i> KYC Rejected</span>
                                        @else
                                            <span class="badge bg-warning text-dark px-3 py-2 fs-7"><i class="bi bi-clock-history me-1"></i> Pending Verification</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="card-body">
                                    @if($kycStatus === 'rejected' && ($provider->rejection_reason || $profile?->rejection_reason))
                                        <div class="alert alert-danger py-2 px-3 mb-3 d-flex align-items-center">
                                            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                                            <div>
                                                <strong>Rejection Reason:</strong> {{ $provider->rejection_reason ?: $profile?->rejection_reason }}
                                            </div>
                                        </div>
                                    @endif

                                    <div class="row g-4">
                                        <!-- Card A: Identity Document -->
                                        <div class="col-md-6">
                                            <div class="card h-100 border rounded-3 bg-white">
                                                <div class="card-header bg-light py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                                                    <span class="fw-bold text-dark"><i class="bi bi-card-heading text-primary me-1"></i> Card A: Identity Document</span>
                                                    <span class="badge bg-secondary text-capitalize">{{ str_replace('_', ' ', $provider->document_type ?: ($profile?->document_type ?: 'Document')) }}</span>
                                                </div>
                                                <div class="card-body text-center p-3">
                                                    <div class="mb-2 text-start small">
                                                        <div><strong>Type:</strong> {{ ucfirst(str_replace('_', ' ', $provider->document_type ?: ($profile?->document_type ?: 'Not Provided'))) }}</div>
                                                        <div><strong>Number:</strong> <code>{{ $provider->document_number ?: ($profile?->document_number ?: 'Not Provided') }}</code></div>
                                                    </div>
                                                    @php
                                                        $idDocFile = $provider->identity_document ?: ($profile?->identity_document ?: ($provider->service_license ?: $profile?->service_license));
                                                        $idDocUrl = $idDocFile ? asset('uploads/identity_documents/' . $idDocFile) : null;
                                                        if ($idDocFile && !file_exists(public_path('uploads/identity_documents/' . $idDocFile)) && file_exists(public_path('uploads/license_files/' . $idDocFile))) {
                                                            $idDocUrl = asset('uploads/license_files/' . $idDocFile);
                                                        }
                                                    @endphp
                                                    @if($idDocUrl)
                                                        <a href="{{ $idDocUrl }}" target="_blank" title="Click to view full size">
                                                            <img src="{{ $idDocUrl }}" class="img-fluid rounded border shadow-xs" style="max-height: 260px; object-fit: contain; width: 100%;" alt="Identity Document">
                                                        </a>
                                                        <div class="mt-2">
                                                            <a href="{{ $idDocUrl }}" target="_blank" class="btn btn-xs btn-outline-secondary">
                                                                <i class="bi bi-arrows-fullscreen me-1"></i> Open Full Document
                                                            </a>
                                                        </div>
                                                    @else
                                                        <div class="p-4 bg-light rounded text-muted">
                                                            <i class="bi bi-file-earmark-x fs-1 d-block mb-2 text-secondary"></i>
                                                            <span>No Identity Document Uploaded</span>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Card B: Live Selfie -->
                                        <div class="col-md-6">
                                            <div class="card h-100 border rounded-3 bg-white">
                                                <div class="card-header bg-light py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                                                    <span class="fw-bold text-dark"><i class="bi bi-camera-fill text-success me-1"></i> Card B: Live Camera Selfie</span>
                                                    <span class="badge bg-info text-white">Live Capture</span>
                                                </div>
                                                <div class="card-body text-center p-3">
                                                    <div class="mb-2 text-start small">
                                                        <div><strong>Captured Date:</strong> {{ optional($provider->created_at)->format('d M Y, h:i A') }}</div>
                                                        <div><strong>Provider Name:</strong> {{ $provider->name }}</div>
                                                    </div>
                                                    @php
                                                        $liveImgFile = $provider->live_image ?: ($profile?->live_image ?: $provider->profile_image);
                                                        $liveImgUrl = $liveImgFile ? asset('uploads/live_selfies/' . $liveImgFile) : null;
                                                        if ($liveImgFile && !file_exists(public_path('uploads/live_selfies/' . $liveImgFile)) && file_exists(public_path('uploads/profile_images/' . $liveImgFile))) {
                                                            $liveImgUrl = asset('uploads/profile_images/' . $liveImgFile);
                                                        }
                                                    @endphp
                                                    @if($liveImgUrl)
                                                        <a href="{{ $liveImgUrl }}" target="_blank" title="Click to view full size">
                                                            <img src="{{ $liveImgUrl }}" class="img-fluid rounded border shadow-xs" style="max-height: 260px; object-fit: contain; width: 100%;" alt="Live Selfie">
                                                        </a>
                                                        <div class="mt-2">
                                                            <a href="{{ $liveImgUrl }}" target="_blank" class="btn btn-xs btn-outline-secondary">
                                                                <i class="bi bi-arrows-fullscreen me-1"></i> Open Full Image
                                                            </a>
                                                        </div>
                                                    @else
                                                        <div class="p-4 bg-light rounded text-muted">
                                                            <i class="bi bi-person-x fs-1 d-block mb-2 text-secondary"></i>
                                                            <span>No Live Selfie Uploaded</span>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Verification Actions Footer -->
                                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectKycModal">
                                            <i class="bi bi-x-circle me-1"></i> Reject KYC
                                        </button>
                                        <form action="{{ route('providers.approve_kyc', $provider->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to approve this provider identity? Provider status will become ACTIVE.');">
                                            @csrf
                                            <button type="submit" class="btn btn-success">
                                                <i class="bi bi-check-circle-fill me-1"></i> Approve KYC & Activate Provider
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal for KYC Rejection -->
                            <div class="modal fade" id="rejectKycModal" tabindex="-1" aria-labelledby="rejectKycModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <form action="{{ route('providers.reject_kyc', $provider->id) }}" method="POST">
                                        @csrf
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title text-danger" id="rejectKycModalLabel">
                                                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Reject Provider KYC
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p class="small text-muted mb-3">Please specify the reason for rejecting this provider's identity verification. The provider will be notified and prompted to re-upload.</p>
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Rejection Reason <span class="text-danger">*</span></label>
                                                    <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="e.g. Identity document is blurry or photo does not match the live selfie."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Confirm Rejection</button>
                                            </div>
                                        </div>
                                    </form>
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
