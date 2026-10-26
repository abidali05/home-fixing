@extends('layouts.app')

@section('title', 'Company Management')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm border-0" style="border-radius: 20px;">
                        <div class="card-header pb-0 bg-transparent border-0 d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 text-dark font-weight-bold" style="font-size: 1.1rem;">Company Accounts</h6>
                                <p class="text-muted text-xs mb-0">Create and manage company profiles, monitor performance, and assign service providers.</p>
                            </div>
                            <a href="{{ route('companies.create') }}" class="btn btn-sm btn-primary border-radius-lg px-3" style="background-color: #4F2396 !important; border-color: #4F2396 !important;">
                                <i class="bi bi-plus-lg me-1"></i> Add Company
                            </a>
                        </div>
                        <div class="card-body px-4 pt-3 pb-3">
                            <div class="table-responsive">
                                @if (session('success'))
                                    <div class="alert alert-success alert-dismissible fade show text-white" role="alert">
                                        {{ session('success') }}
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                @endif

                                @if (session('error'))
                                    <div class="alert alert-danger alert-dismissible fade show text-white" role="alert">
                                        {{ session('error') }}
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                @endif

                                <table class="table table-bordered table-striped table-hover mb-0 align-middle text-sm" id="CompaniesTable">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>S.No</th>
                                            <th>Company Name</th>
                                            <th>Contact Person</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Providers</th>
                                            <th>Status</th>
                                            <th style="width: 15%">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Delete Company Modal -->
    <div class="modal fade" id="deleteCompanyModal" tabindex="-1" aria-labelledby="deleteCompanyModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteCompanyModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this Company Account? Any associated providers will be dissociated.
                    <input type="hidden" id="deleteCompanyId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmDeleteCompanyBtn" class="btn btn-danger">Delete</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            let table = $('#CompaniesTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: "{{ route('companies.index') }}",
                columns: [
                    { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                    { data: 'company_name', name: 'company_name' },
                    { data: 'name', name: 'name' },
                    { data: 'email', name: 'email' },
                    { data: 'phone', name: 'phone' },
                    { data: 'providers_count', name: 'providers_count', orderable: false, searchable: false },
                    { data: 'status', name: 'status' },
                    { data: 'action', name: 'action', orderable: false, searchable: false }
                ]
            });

            // Handle Delete click
            $(document).on('click', '.deleteCompanyBtn', function() {
                let id = $(this).data('id');
                $('#deleteCompanyId').val(id);
                let modal = new bootstrap.Modal(document.getElementById('deleteCompanyModal'));
                modal.show();
            });

            // Confirm Delete
            $('#confirmDeleteCompanyBtn').on('click', function() {
                let id = $('#deleteCompanyId').val();
                $.ajax({
                    url: `/admin/companies/${id}`,
                    method: 'DELETE',
                    data: {
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(response) {
                        $('#deleteCompanyModal').modal('hide');
                        table.ajax.reload();
                        // Bootstrap alert toast or reload
                        location.reload();
                    },
                    error: function() {
                        alert('Something went wrong. Please try again.');
                    }
                });
            });
        });
    </script>
@endpush
