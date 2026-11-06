@extends('layouts.app')

@section('title', 'Assign Providers to Company')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card shadow-sm border-0" style="border-radius: 20px;">
                        <div class="card-header bg-transparent pb-0 d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 text-dark font-weight-bold" style="font-size: 1.1rem;">Assign Providers to {{ $company->company_name }}</h6>
                                <p class="text-muted text-xs mb-0">Select the service providers that belong under this company's management.</p>
                            </div>
                            <a href="{{ route('companies.index') }}" class="btn btn-sm btn-secondary border-radius-lg px-3">
                                <i class="bi bi-arrow-left me-1"></i> Back
                            </a>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('companies.assign', $company->id) }}" method="POST">
                                @csrf

                                <div class="mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                                        <input type="text" id="providerSearchInput" class="form-control border-start-0" placeholder="Search service providers by name or phone...">
                                    </div>
                                </div>

                                <div class="table-responsive border border-radius-lg p-2 mt-3" style="max-height: 400px; overflow-y: auto;">
                                    <table class="table table-hover table-striped align-middle text-sm mb-0">
                                        <thead class="bg-light sticky-top">
                                            <tr>
                                                <th style="width: 5%">
                                                    <input type="checkbox" id="selectAllProviders" class="form-check-input">
                                                </th>
                                                <th>Provider Name</th>
                                                <th>Phone Number</th>
                                                <th>Current Company Association</th>
                                            </tr>
                                        </thead>
                                        <tbody id="providersTableBody">
                                            @forelse($providers as $provider)
                                                <tr class="provider-row">
                                                    <td>
                                                        <input type="checkbox" name="providers[]" value="{{ $provider->id }}" class="form-check-input provider-checkbox" {{ in_array($provider->id, $assignedProviderIds) ? 'checked' : '' }}>
                                                    </td>
                                                    <td class="provider-name-cell">
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar avatar-sm me-3 bg-light rounded-circle text-center d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                                @if($provider->profile_image)
                                                                    <img src="{{ $provider->profile_image }}" class="rounded-circle w-100 h-100" style="object-fit: cover;">
                                                                @else
                                                                    <span class="text-xs font-weight-bold text-secondary">{{ strtoupper(substr($provider->name, 0, 2)) }}</span>
                                                                @endif
                                                            </div>
                                                            <div>
                                                                <h6 class="mb-0 text-xs font-weight-bold">{{ $provider->name }}</h6>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="provider-phone-cell text-xs font-weight-semibold text-secondary">{{ $provider->phone }}</td>
                                                    <td class="text-xs">
                                                        @if($provider->company_id)
                                                            @if($provider->company_id == $company->id)
                                                                <span class="badge bg-soft-success text-success" style="background-color: #e2f9ec;">Assigned to this company</span>
                                                            @else
                                                                @php
                                                                    $otherCompany = App\Models\AdminUsers::find($provider->company_id);
                                                                @endphp
                                                                <span class="badge bg-soft-warning text-warning" style="background-color: #fff3cd;">Assigned to: {{ $otherCompany->company_name ?? 'Other Company' }}</span>
                                                            @endif
                                                        @else
                                                            <span class="badge bg-soft-secondary text-secondary" style="background-color: #f8f9fa;">Independent / Free Agent</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="text-center text-muted py-4">No active service providers found.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                <div class="d-flex justify-content-end mt-4">
                                    <button type="submit" class="btn btn-primary border-radius-lg px-4" style="background-color: #4F2396 !important; border-color: #4F2396 !important;">
                                        <i class="bi bi-check-circle-fill me-2"></i> Save Assignments
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
    <script>
        $(document).ready(function() {
            // Select/Deselect All Checkboxes
            $('#selectAllProviders').on('change', function() {
                let isChecked = $(this).prop('checked');
                $('.provider-checkbox').prop('checked', isChecked);
            });

            // Sync Select All checkbox state
            $(document).on('change', '.provider-checkbox', function() {
                if ($('.provider-checkbox:checked').length === $('.provider-checkbox').length) {
                    $('#selectAllProviders').prop('checked', true);
                } else {
                    $('#selectAllProviders').prop('checked', false);
                }
            });

            // Search filter functionality
            $('#providerSearchInput').on('keyup', function() {
                let query = $(this).val().toLowerCase();
                
                $('.provider-row').each(function() {
                    let name = $(this).find('.provider-name-cell').text().toLowerCase();
                    let phone = $(this).find('.provider-phone-cell').text().toLowerCase();

                    if (name.includes(query) || phone.includes(query)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });
        });
    </script>
@endpush
