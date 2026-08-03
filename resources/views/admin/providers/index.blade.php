@extends('layouts.app')

@section('title', 'Providers')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm admin-panel-card">
                        <div class="card-header admin-panel-header d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="admin-panel-title">Providers</h6>
                                <p class="admin-panel-subtitle">Manage provider approvals, visibility, and service details.</p>
                            </div>
                            @if(!$isCompany)
                                <a href="{{ route('providers.create') }}" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-lg me-1"></i> Add New Provider
                                </a>
                            @endif
                        </div>
                        <div class="card-body">
                            @if (session('success'))
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    {{ session('success') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

                            @if (session('error'))
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    {{ session('error') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

                            <form method="GET" action="{{ route('providers.index') }}" class="row g-3 admin-filter-card admin-loader-form">
                                <div class="col-md-4">
                                    <label class="form-label">Search</label>
                                    <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                                        placeholder="Name, email, phone, company">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select select2">
                                        <option value="">All Statuses</option>
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                                                {{ ucfirst($status) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">City</label>
                                    <select name="city_id" class="form-select select2">
                                        <option value="">All Cities</option>
                                        @foreach ($cities as $city)
                                            <option value="{{ $city->id }}" {{ (string) request('city_id') === (string) $city->id ? 'selected' : '' }}>
                                                {{ $city->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Service</label>
                                    <select name="category_id" class="form-select select2">
                                        <option value="">All Services</option>
                                        @foreach ($services as $service)
                                            <option value="{{ $service->id }}" {{ (string) request('category_id') === (string) $service->id ? 'selected' : '' }}>
                                                {{ $service->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Provider Type</label>
                                    <select name="provider_type" class="form-select">
                                        <option value="">All Types</option>
                                        @foreach ($providerTypes as $providerType)
                                            <option value="{{ $providerType }}" {{ request('provider_type') === $providerType ? 'selected' : '' }}>
                                                {{ ucfirst($providerType) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">From</label>
                                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">To</label>
                                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
                                </div>
                                <div class="col-md-8 admin-filter-actions">
                                    <button type="submit" class="btn btn-primary mb-0">Apply Filters</button>
                                    <a href="{{ route('providers.index') }}" class="btn btn-outline-secondary mb-0">Clear</a>
                                </div>
                            </form>

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle admin-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Provider</th>
                                            <th>Contact</th>
                                            <th>Company</th>
                                            <th>City</th>
                                            <th>Services</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Registered</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($providers as $provider)
                                            @php
                                                $profile = $provider->providerProfile;
                                                $serviceIds = is_array($profile?->service_category) ? $profile?->service_category : [];
                                                $serviceNames = \App\Models\Admin\ServiceCategoryModel::whereIn('id', $serviceIds)->pluck('name')->toArray();
                                                $badgeClass = match ($provider->provider_status) {
                                                    'active' => 'bg-success',
                                                    'inactive' => 'bg-secondary',
                                                    'suspended' => 'bg-warning',
                                                    'banned' => 'bg-danger',
                                                    default => 'bg-dark',
                                                };
                                            @endphp
                                            <tr>
                                                <td>#{{ $provider->id }}</td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="{{ $provider->profile_image ? asset('uploads/profile_images/' . $provider->profile_image) : asset('assets/img/default.jpg') }}"
                                                            alt="{{ $provider->name }}" class="admin-avatar me-2">
                                                        <div>
                                                            <div class="fw-semibold">{{ $provider->name }}</div>
                                                            <small class="text-muted">{{ $profile?->company_name ?: ($provider->company_name ?: 'Individual Provider') }}</small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>{{ $provider->email ?: '-' }}</div>
                                                    <small class="text-muted">{{ $provider->phone }}</small>
                                                </td>
                                                <td>
                                                    @if($isCompany)
                                                        <span class="badge bg-light text-primary border"><i class="bi bi-building me-1"></i> {{ optional(Auth::guard('admin')->user())->name }}</span>
                                                    @else
                                                        @php
                                                            $assignedCompany = $companies->firstWhere('id', $provider->company_id);
                                                            $reqCompany = $provider->company_name ?: ($profile?->company_name ?? null);
                                                        @endphp
                                                        <form action="{{ route('providers.assign_company', $provider->id) }}" method="POST" class="admin-loader-form">
                                                            @csrf
                                                            <select name="company_id" class="form-select form-select-sm admin-auto-submit mb-1" style="min-width: 140px;">
                                                                <option value="">-- No Company --</option>
                                                                @foreach($companies as $c)
                                                                    <option value="{{ $c->id }}" {{ $provider->company_id == $c->id ? 'selected' : '' }}>
                                                                        {{ $c->name }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                            @if($reqCompany)
                                                                <small class="text-muted d-block" style="font-size: 0.72rem;"><i class="bi bi-info-circle me-1"></i>Requested: <strong>{{ $reqCompany }}</strong></small>
                                                            @endif
                                                        </form>
                                                    @endif
                                                </td>
                                                <td>{{ $provider->cityname }}</td>
                                                <td>
                                                    @forelse ($serviceNames as $serviceName)
                                                        <span class="badge bg-light text-dark border me-1">{{ $serviceName }}</span>
                                                    @empty
                                                        <span class="text-muted">No services</span>
                                                    @endforelse
                                                </td>
                                                <td>{{ ucfirst($profile?->provider_type ?: 'individual') }}</td>
                                                <td class="text-center admin-status-cell">
                                                    <form action="{{ route('providers.status', $provider->id) }}" method="POST" class="admin-status-form admin-loader-form">
                                                        @csrf
                                                        <select name="provider_status" class="form-select form-select-sm admin-auto-submit">
                                                            @foreach ($statuses as $status)
                                                                <option value="{{ $status }}" {{ $provider->provider_status === $status ? 'selected' : '' }}>
                                                                    {{ ucfirst($status) }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </form>
                                                    {{-- <div class="mt-2">
                                                        <span class="badge admin-badge {{ $badgeClass }}">{{ ucfirst($provider->provider_status ?: 'inactive') }}</span>
                                                    </div> --}}
                                                </td>
                                                <td>{{ optional($provider->created_at)->format('d M Y') }}</td>
                                                <td class="text-center admin-action-cell">
                                                    <div class="admin-icon-actions">
                                                        <a href="javascript:void(0);" class="admin-icon-btn warning open-direct-notification-modal" data-user-id="{{ $provider->id }}" data-user-name="{{ $provider->name }}" title="Send Push Notification">
                                                            <i class="bi bi-bell-fill"></i>
                                                        </a>
                                                        <a href="{{ route('providers.show', $provider->id) }}" class="admin-icon-btn info" title="View provider">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <a href="{{ route('providers.edit', $provider->id) }}" class="admin-icon-btn primary" title="Edit provider">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="9" class="text-center text-muted py-4">No providers found for the selected filters.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <small class="admin-pagination-summary">
                                    Showing {{ $providers->firstItem() ?? 0 }} to {{ $providers->lastItem() ?? 0 }} of {{ $providers->total() }} providers
                                </small>
                                {{ $providers->links('pagination::bootstrap-5') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

<!-- Direct Push Notification Modal -->
<div class="modal fade" id="sendDirectNotificationModal" tabindex="-1" aria-labelledby="sendDirectNotificationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px;">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-2">
                    <div class="avatar avatar-sm rounded-circle text-center" style="background: linear-gradient(135deg, #4F2396 0%, #682eb8 100%); width: 36px; height: 36px; line-height: 36px;">
                        <i class="bi bi-bell-fill text-white"></i>
                    </div>
                    <div>
                        <h6 class="modal-title font-weight-bold text-dark" id="sendDirectNotificationModalLabel">Send Push Notification</h6>
                        <p class="text-xs text-muted mb-0" id="directModalTargetUser">Target Provider</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="directNotificationForm" action="{{ route('admin.notifications.send_direct') }}" method="POST">
                @csrf
                <input type="hidden" name="user_id" id="directModalUserId">
                <div class="modal-body pt-3">
                    <div class="mb-3">
                        <label for="direct_event_type" class="form-label text-xs font-weight-bold text-dark">Notification / Event Type</label>
                        <select name="event_type" id="direct_event_type" class="form-select form-select-sm">
                            <option value="system_alert">🔔 System Alert / General Notice</option>
                            <option value="promotional">🏷️ Promotional Offer / Discount</option>
                            <option value="event_update">📅 Event / Status Update</option>
                            <option value="account_notice">🔒 Account Security & Status</option>
                            <option value="custom_event">⚡ Custom Payload Event</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="direct_title" class="form-label text-xs font-weight-bold text-dark">Notification Title</label>
                        <input type="text" name="title" id="direct_title" class="form-control form-control-sm" placeholder="e.g. Service Update Notice" required>
                    </div>
                    <div class="mb-3">
                        <label for="direct_body" class="form-label text-xs font-weight-bold text-dark">Message Body</label>
                        <textarea name="body" id="direct_body" class="form-control form-control-sm" rows="3" placeholder="Enter message description..." required></textarea>
                    </div>
                    <div id="directNotificationAlert" class="alert d-none text-xs text-white p-2 mb-0" role="alert"></div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="directNotificationSubmitBtn" style="background-color: #4F2396 !important; border-color: #4F2396 !important;">
                        <i class="bi bi-send-fill me-1"></i> Send Now
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        $(document).on('click', '.open-direct-notification-modal', function() {
            var userId = $(this).data('user-id');
            var userName = $(this).data('user-name');

            $('#directModalUserId').val(userId);
            $('#directModalTargetUser').text('To: ' + userName + ' (Provider ID #' + userId + ')');
            $('#directNotificationAlert').addClass('d-none').removeClass('alert-success alert-danger');
            $('#sendDirectNotificationModal').modal('show');
        });

        $('#directNotificationForm').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var submitBtn = $('#directNotificationSubmitBtn');
            var alertBox = $('#directNotificationAlert');

            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status"></span> Sending...');

            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: form.serialize(),
                success: function(response) {
                    submitBtn.prop('disabled', false).html('<i class="bi bi-send-fill me-1"></i> Send Now');
                    if (response.success) {
                        alertBox.removeClass('d-none alert-danger').addClass('alert-success').text(response.message);
                        setTimeout(function() {
                            $('#sendDirectNotificationModal').modal('hide');
                            form[0].reset();
                        }, 1500);
                    } else {
                        alertBox.removeClass('d-none alert-success').addClass('alert-danger').text(response.message || 'Error sending notification.');
                    }
                },
                error: function(xhr) {
                    submitBtn.prop('disabled', false).html('<i class="bi bi-send-fill me-1"></i> Send Now');
                    alertBox.removeClass('d-none alert-success').addClass('alert-danger').text('Failed to send notification. Please try again.');
                }
            });
        });
    </script>
@endpush
@endsection
