@extends('layouts.app')

@section('title', 'Users')

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
                                <h6 class="mb-0">Users</h6>
                                <a href="{{ route('users.create') }}" type="button" class="btn btn-sm btn-primary {{ in_array(15, $rolePermissions) ? '' : 'd-none' }}">
                                    <i class="bi bi-plus-lg me-1"></i> Add New User
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
                                    id="UsersTable">
                                    <thead class="thead-light">
                                        <tr>
                                            <th style="width: 10%">S.No</th>
                                            <th>Image</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>City</th>
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
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteUserModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this User?
                <input type="hidden" id="deleteUserId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="confirmDeleteUserBtn" class="btn btn-danger">Delete</button>
            </div>
        </div>
    </div>
</div>

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
                        <p class="text-xs text-muted mb-0" id="directModalTargetUser">Target Account</p>
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
                        <input type="text" name="title" id="direct_title" class="form-control form-control-sm" placeholder="e.g. Account Update Notice" required>
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

@endsection

@push('scripts')
    <script>
        const SystemUsersDataUrl = "{{ route('users.index') }}";

        $(document).on('click', '.open-direct-notification-modal', function() {
            var userId = $(this).data('user-id');
            var userName = $(this).data('user-name');

            $('#directModalUserId').val(userId);
            $('#directModalTargetUser').text('To: ' + userName + ' (User ID #' + userId + ')');
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
    <script src="{{ asset('customjs/users/index.js') }}"></script>
@endpush
