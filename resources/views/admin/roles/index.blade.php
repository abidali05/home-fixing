@extends('layouts.app')

@section('title', 'Roles')

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
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Roles</h6>
                    <a href="{{ route('roles.create') }}" class="btn btn-sm btn-primary {{ in_array(7, $rolePermissions) ? '' : 'd-none' }}">
                        <i class="bi bi-plus-lg me-1"></i> Add Role
                    </a>
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

                    <table class="table table-bordered table-hover text-sm" id="rolesTable">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 5%">S.No</th>
                                <th>Name</th>
                                <th>Created Date</th>
                                <th style="width: 15%">Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </main>




    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteRoleModal" tabindex="-1" aria-labelledby="deleteRoleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteRoleModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this role?
                    <input type="hidden" id="deleteRoleId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmDeleteRoleBtn" class="btn btn-danger">Delete</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        const RolesDataUrl = "{{ route('roles.index') }}";
    </script>
    <script src="{{ asset('customjs/roles/index.js') }}"></script>
@endpush
