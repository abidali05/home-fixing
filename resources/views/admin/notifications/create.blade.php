@extends('layouts.app')

@section('title', 'Push Notifications Hub')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show text-white" role="alert" style="background: #2dce89;">
                    <span class="alert-icon"><i class="bi bi-check-circle-fill"></i></span>
                    <span class="alert-text"><strong>Success!</strong> {{ session('success') }}</span>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show text-white" role="alert" style="background: #f5365c;">
                    <span class="alert-icon"><i class="bi bi-exclamation-triangle-fill"></i></span>
                    <span class="alert-text"><strong>Error!</strong> {{ session('error') }}</span>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            <div class="row g-4">
                <!-- Form Column -->
                <div class="col-lg-12">
                    <div class="card shadow-sm border-0" style="border-radius: 20px;">
                        <div class="card-header bg-transparent pb-0">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="mb-0 text-dark font-weight-bold" style="font-size: 1.15rem;">FCM Push Notification Hub</h6>
                                    <p class="text-muted text-xs mt-1 mb-0">Dispatch FCM push notifications and in-app inbox alerts to group audiences or specific users.</p>
                                </div>
                                <span class="badge" style="background: linear-gradient(135deg, #4F2396 0%, #682eb8 100%); padding: 8px 14px; border-radius: 12px; font-size: 0.75rem;">
                                    <i class="bi bi-broadcast me-1"></i> FCM Engine Active
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('admin.notifications.store') }}" method="POST" class="admin-loader-form">
                                @csrf

                                <!-- Target Mode Toggle -->
                                <div class="mb-4">
                                    <label class="form-label text-dark font-weight-semibold">Target Mode</label>
                                    <div class="d-flex gap-3">
                                        <div class="form-check custom-option-pill">
                                            <input class="form-check-input" type="radio" name="target_mode" id="mode_group" value="group" {{ old('target_mode', 'group') === 'group' ? 'checked' : '' }}>
                                            <label class="form-check-label font-weight-bold" for="mode_group">
                                                <i class="bi bi-people-fill me-1 text-primary"></i> Group Broadcast
                                            </label>
                                        </div>
                                        <div class="form-check custom-option-pill">
                                            <input class="form-check-input" type="radio" name="target_mode" id="mode_specific" value="specific" {{ old('target_mode') === 'specific' ? 'checked' : '' }}>
                                            <label class="form-check-label font-weight-bold" for="mode_specific">
                                                <i class="bi bi-person-check-fill me-1 text-success"></i> Specific User(s)
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Group Audience Selection -->
                                <div class="mb-4" id="group_target_container">
                                    <label for="target_audience" class="form-label text-dark font-weight-semibold">Select Target Group</label>
                                    <select name="target_audience" id="target_audience" class="form-select select2">
                                        <option value="all" {{ old('target_audience') == 'all' ? 'selected' : '' }}>🌐 All Registered Users (Customers, Providers & Sellers)</option>
                                        <option value="0" {{ old('target_audience') == '0' ? 'selected' : '' }}>👤 Customers / Users Only</option>
                                        <option value="1" {{ old('target_audience') == '1' ? 'selected' : '' }}>🛠️ Service Providers Only</option>
                                        <option value="2" {{ old('target_audience') == '2' ? 'selected' : '' }}>🏪 Marketplace Sellers Only</option>
                                    </select>
                                    @error('target_audience')
                                        <small class="text-danger d-block mt-1">{{ $message }}</small>
                                    @enderror
                                </div>

                                <!-- Specific Users Selection -->
                                <div class="mb-4 d-none" id="specific_target_container">
                                    <div class="row g-3">
                                        <div class="col-md-5">
                                            <label for="specific_role_filter" class="form-label text-dark font-weight-semibold">1. Filter User Role / Category</label>
                                            <select id="specific_role_filter" class="form-select select2">
                                                <option value="all">🌐 All Accounts (Customers, Providers & Sellers)</option>
                                                <option value="0">👤 Customers / Users Only</option>
                                                <option value="1">🛠️ Service Providers Only</option>
                                                <option value="2">🏪 Marketplace Sellers Only</option>
                                            </select>
                                        </div>
                                        <div class="col-md-7">
                                            <label for="user_ids" class="form-label text-dark font-weight-semibold">2. Select Target User(s)</label>
                                            <select name="user_ids[]" id="user_ids" class="form-select select2" multiple data-placeholder="Search by name, phone or email...">
                                                @foreach($usersList as $u)
                                                    @php
                                                        $roleVal = '0';
                                                        $roleLabel = 'Customer';
                                                        if ((string)$u->role === '1' || str_contains((string)$u->has_roles, '1')) {
                                                            $roleVal = '1';
                                                            $roleLabel = 'Provider';
                                                        } elseif ((string)$u->role === '2' || str_contains((string)$u->has_roles, '2')) {
                                                            $roleVal = '2';
                                                            $roleLabel = 'Seller';
                                                        }
                                                    @endphp
                                                    <option value="{{ $u->id }}" data-role="{{ $roleVal }}">
                                                        [{{ $roleLabel }}] {{ $u->name }} ({{ $u->phone ?? $u->email }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <small class="text-muted d-block mt-2"><i class="bi bi-info-circle me-1"></i>Select a role first to narrow down accounts, then search and select single or multiple users.</small>
                                    @error('user_ids')
                                        <small class="text-danger d-block mt-1">{{ $message }}</small>
                                    @enderror
                                </div>

                                <!-- Event / Notification Type -->
                                <div class="mb-4">
                                    <label for="event_type" class="form-label text-dark font-weight-semibold">Notification / Event Type</label>
                                    <select name="event_type" id="event_type" class="form-select select2" required>
                                        <option value="system_alert" {{ old('event_type') == 'system_alert' ? 'selected' : '' }}>🔔 System Alert / General Notice</option>
                                        <option value="promotional" {{ old('event_type') == 'promotional' ? 'selected' : '' }}>🏷️ Promotional Offer / Discount</option>
                                        <option value="event_update" {{ old('event_type') == 'event_update' ? 'selected' : '' }}>📅 Event / Status Update</option>
                                        <option value="account_notice" {{ old('event_type') == 'account_notice' ? 'selected' : '' }}>🔒 Account Security & Status</option>
                                        <option value="custom_event" {{ old('event_type') == 'custom_event' ? 'selected' : '' }}>⚡ Custom Payload Event</option>
                                    </select>
                                    @error('event_type')
                                        <small class="text-danger d-block mt-1">{{ $message }}</small>
                                    @enderror
                                </div>

                                <!-- Title Input -->
                                <div class="mb-4">
                                    <label for="title" class="form-label text-dark font-weight-semibold">Notification Title</label>
                                    <input type="text" name="title" id="title_input" class="form-control" placeholder="e.g. Special Discount Announcement!" value="{{ old('title') }}" required>
                                    @error('title')
                                        <small class="text-danger d-block mt-1">{{ $message }}</small>
                                    @enderror
                                </div>

                                <!-- Message Body -->
                                <div class="mb-4">
                                    <label for="body" class="form-label text-dark font-weight-semibold">Message Body</label>
                                    <textarea name="body" id="body_input" class="form-control" rows="4" placeholder="Write your detailed push notification description here..." required>{{ old('body') }}</textarea>
                                    @error('body')
                                        <small class="text-danger d-block mt-1">{{ $message }}</small>
                                    @enderror
                                </div>

                                <!-- Optional Payload -->
                                <div class="mb-4">
                                    <label for="custom_payload" class="form-label text-dark font-weight-semibold">Custom Payload / Link (Optional)</label>
                                    <input type="text" name="custom_payload" id="custom_payload" class="form-control" placeholder="e.g. https://app.example.com/promo or json payload" value="{{ old('custom_payload') }}">
                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-4">
                                    <button type="reset" class="btn btn-secondary border-radius-lg px-4">Reset</button>
                                    <button type="submit" class="btn btn-primary border-radius-lg px-4" style="background-color: #4F2396 !important; border-color: #4F2396 !important;">
                                        <i class="bi bi-send-fill me-2"></i> Send Notification
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const modeGroup = document.getElementById('mode_group');
                const modeSpecific = document.getElementById('mode_specific');
                const groupContainer = document.getElementById('group_target_container');
                const specificContainer = document.getElementById('specific_target_container');

                function toggleTargetMode() {
                    if (modeSpecific && modeSpecific.checked) {
                        groupContainer.classList.add('d-none');
                        specificContainer.classList.remove('d-none');
                    } else {
                        specificContainer.classList.add('d-none');
                        groupContainer.classList.remove('d-none');
                    }
                }

                if (modeGroup) modeGroup.addEventListener('change', toggleTargetMode);
                if (modeSpecific) modeSpecific.addEventListener('change', toggleTargetMode);

                toggleTargetMode();

                // Real-time Preview Binding
                const titleInput = document.getElementById('title_input');
                const bodyInput = document.getElementById('body_input');
                const eventTypeSelect = document.getElementById('event_type');

                const previewTitle = document.getElementById('preview_title');
                const previewBody = document.getElementById('preview_body');
                const previewBadge = document.getElementById('preview_event_badge');

                if (titleInput) {
                    titleInput.addEventListener('input', function() {
                        previewTitle.textContent = this.value.trim() || 'Notification Title Preview';
                    });
                }

                if (bodyInput) {
                    bodyInput.addEventListener('input', function() {
                        previewBody.textContent = this.value.trim() || 'Your message content description will render right here in real time as you type.';
                    });
                }

                if (eventTypeSelect) {
                    eventTypeSelect.addEventListener('change', function() {
                        const selectedText = this.options[this.selectedIndex].text.replace(/^[^\w\s]+/, '').trim();
                        previewBadge.textContent = selectedText;
                    });
                }
            });

            $(document).ready(function() {
                const $userSelect = $('#user_ids');
                const $roleFilter = $('#specific_role_filter');
                const allUserOptions = [];

                $userSelect.find('option').each(function() {
                    allUserOptions.push({
                        id: $(this).val(),
                        text: $(this).text(),
                        role: $(this).attr('data-role') || '0'
                    });
                });

                if ($roleFilter.length) {
                    $roleFilter.on('change', function() {
                        const selectedRole = $(this).val();
                        const currentSelections = $userSelect.val() || [];
                        $userSelect.empty();

                        allUserOptions.forEach(opt => {
                            if (selectedRole === 'all' || String(opt.role) === String(selectedRole)) {
                                const isSelected = currentSelections.includes(String(opt.id));
                                const newOpt = new Option(opt.text, opt.id, isSelected, isSelected);
                                $(newOpt).attr('data-role', opt.role);
                                $userSelect.append(newOpt);
                            }
                        });

                        $userSelect.trigger('change');
                    });
                }
            });
        </script>
    @endpush
@endsection
