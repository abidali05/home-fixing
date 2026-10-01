@extends('layouts.app')

@section('title', 'Account Active Request')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header pb-0">
                            <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Account Active Requests</h6>
                            </div>
                        </div>
                        <div class="card-body px-4 pt-3 pb-3">
                            <div class="table-responsive">
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
                                <table class="table table-bordered table-striped table-hover mb-0 align-middle text-sm"
                                    id="ActiveRequestsTable">
                                    <thead class="thead-light">
                                        <tr>
                                            <th style="width: 10%">S.No</th>
                                            <th>User Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Message</th>
                                            <th>Submitted At</th>
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

<!-- Activate User Modal -->
<div class="modal fade" id="activateUserModal" tabindex="-1" aria-labelledby="activateUserModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="activateUserModalLabel">Confirm Activation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to activate this user account?
                <input type="hidden" id="activateRequestId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="confirmActivateUserBtn" class="btn btn-success">Activate</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function () {
            const table = $("#ActiveRequestsTable").DataTable({
                processing: true,
                serverSide: true,
                pagingType: "simple_numbers",
                scrollX: false,
                responsive: true,
                ajax: "{{ route('account_active_requests.index') }}",
                columns: [
                    { data: "DT_RowIndex", name: "DT_RowIndex" },
                    { data: "user_name", name: "user_name" },
                    { data: "user_email", name: "user_email" },
                    { data: "user_phone", name: "user_phone" },
                    { data: "message", name: "message" },
                    { data: "created_at", name: "created_at" },
                    {
                        data: "action",
                        name: "action",
                        orderable: false,
                        searchable: false,
                    },
                ],
                language: {
                    emptyTable: "No active requests found",
                    zeroRecords: "No matching requests found",
                    processing: "Loading...",
                    paginate: {
                        previous: '<i class="bi bi-chevron-left"></i>',
                        next: '<i class="bi bi-chevron-right"></i>',
                    },
                },
            });

            // Activate button click
            $(document).on("click", ".activateUserBtn", function () {
                const id = $(this).data("id");
                $("#activateRequestId").val(id);
                $("#activateUserModal").modal("show");
            });

            // Confirm activate click
            $("#confirmActivateUserBtn").click(function () {
                const id = $("#activateRequestId").val();
                $.ajax({
                    url: `/account-active-requests/activate/${id}`,
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}"
                    },
                    success: function (response) {
                        if (response.status === 200) {
                            toastr.success(response.message, "", { timeOut: 3000 });
                            $("#activateUserModal").modal("hide");
                            table.ajax.reload(null, false);
                        } else {
                            toastr.error(response.message || "Failed to activate", "", { timeOut: 3000 });
                        }
                    },
                    error: function (xhr) {
                        toastr.error("Failed to activate user account.", "", { timeOut: 3000 });
                    }
                });
            });
        });
    </script>
@endpush
