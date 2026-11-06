@extends('layouts.app')

@section('title', 'Job Requests')

@section('content')
    @php
        $rolePermissions = App\Models\Admin\RolePermissions::where('role_id', Auth::guard('admin')->user()->role)
            ->pluck('permission_id')
            ->toArray();
        $allowed_modules = App\Models\Admin\Permission::whereIn('id', $rolePermissions)
            ->pluck('module_name')
            ->unique()
            ->toArray();
    @endphp
    <main class="main-content position-relative border-radius-lg">

        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header pb-0">
                            <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Job Requests</h6>
                                <a href="{{ route('job_requests.create') }}" style="background-color: #4F2396; color: white;" type="button" class="btn btn-sm  {{ in_array(23, $rolePermissions) ? '' : 'd-none' }}">
                                    <i class="bi bi-plus-lg me-1"></i> Add New Request
                                </a>
                            </div>


                        </div>
                        <div class="card-body px-4 pt-3 pb-3">
                            <div class="table-responsive">
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
                                <table class="table table-bordered table-striped table-hover mb-0 align-middle text-sm"
                                    id="JobRequestsTable">
                                    <thead class="thead-light">
                                        <tr>
                                            <th style="width: 10%">S.No</th>
                                            <th>User</th>
                                            <th>Service</th>
                                            <th>Price</th>
                                            <th>Date</th>
                                            <th>Request Date</th>
                                            <th>Status</th>
                                            <th style="width: 15%">Action</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </main>


    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteServiceRequestModal" tabindex="-1" aria-labelledby="deleteServiceRequestModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteServiceRequestModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this Request?
                    <input type="hidden" id="deleteRequestId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmdeleteServiceRequestBtn" class="btn btn-danger">Delete</button>
                </div>
            </div>
        </div>
    </div>
@endsection




@push('scripts')
    <script>
        const JobRequestsDataUrl = "{{ route('job_requests.index') }}";
    </script>
    <script src="{{ asset('customjs/job_requests/index.js') }}"></script>
@endpush
