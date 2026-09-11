@php
    $setting = App\Models\Admin\SystemSettingModel::first();
    $logoUrl = optional($setting)->logo ? asset('uploads/system_settings/' . $setting->logo) : asset('uploads/system_settings/Logo1.png');
    $currentUser = Auth::guard('admin')->user();
@endphp

<nav class="navbar navbar-main navbar-expand-lg px-3 py-2 mx-3 mt-3 shadow-sm rounded-3 border bg-white" id="navbarBlur" data-scroll="false" style="z-index: 1020;">
    <div class="container-fluid px-0 d-flex align-items-center justify-content-between">
        
        <!-- Left Side: Toggle, Logo & Title -->
        <div class="d-flex align-items-center">
            <button class="btn btn-link text-dark p-0 me-3 d-xl-none mb-0" id="iconSidenav">
                <i class="bi bi-list fs-3 text-dark"></i>
            </button>

            <a class="navbar-brand d-flex align-items-center m-0 me-3" href="{{ url('/') }}">
                <img src="{{ $logoUrl }}" class="me-2 shadow-xs" alt="System Logo" style="max-height: 38px; width: auto; border-radius: 8px; object-fit: contain;" />
                <span class="font-weight-bold fs-6 text-dark">{{ optional($setting)->system_name ?? 'Home Fixing' }}</span>
            </a>

            <span class="badge bg-light text-secondary d-none d-md-inline-block border px-2.5 py-1.5 rounded-pill text-xs">
                <i class="bi bi-calendar3 me-1"></i> {{ date('D, d M Y') }}
            </span>
        </div>

        <!-- Right Side: Notification Bell & Admin Profile -->
        <div class="d-flex align-items-center gap-3">
            
            <!-- Notification Bell Dropdown -->
            <div class="dropdown">
                <a href="#" class="position-relative d-flex align-items-center justify-content-center bg-light rounded-circle shadow-xs" 
                   id="topHeaderNotificationsBell" 
                   data-bs-toggle="dropdown" 
                   aria-expanded="false"
                   style="width: 40px; height: 40px; text-decoration: none; transition: all 0.2s ease;">
                    <i class="bi bi-bell-fill fs-5" style="color: #4F2396;"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-circle bg-danger border border-white" 
                          id="topHeaderNotificationBadge" 
                          style="font-size: 0.62rem; padding: 0.3em 0.55em; display: none;">
                        0
                    </span>
                </a>

                <ul class="dropdown-menu dropdown-menu-end py-2 px-3 shadow-lg border-0 mt-2" 
                    aria-labelledby="topHeaderNotificationsBell" 
                    id="topHeaderNotificationDropdown"
                    style="width: 320px; max-width: 90vw; border-radius: 16px; border: 1px solid rgba(79,35,150,0.12) !important;">
                    
                    <li class="dropdown-header text-dark font-weight-bold px-1 py-1 mb-1 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-bell-fill text-primary me-2"></i>
                            <span>Notifications</span>
                        </div>
                        <span class="badge rounded-pill" id="topHeaderNotificationCountHeader" style="background-color: #4F2396; color: #fff; font-size: 0.68rem; display: none;">0</span>
                    </li>
                    
                    <li><hr class="dropdown-divider my-1"></li>
                    
                    <div id="topHeaderNotificationItems" style="max-height: 320px; overflow-y: auto;">
                        <li class="text-center text-muted py-3 small" id="topHeaderNoNotificationsItem">
                            <i class="bi bi-check2-circle text-success fs-5 d-block mb-1"></i>
                            No pending alerts
                        </li>
                    </div>
                </ul>
            </div>

            <!-- User Profile Dropdown -->
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none p-1 rounded-pill bg-light border" 
                   id="userProfileDropdown" 
                   data-bs-toggle="dropdown" 
                   aria-expanded="false">
                    <div class="rounded-circle text-white d-flex align-items-center justify-content-center font-weight-bold me-2 shadow-xs" 
                         style="width: 34px; height: 34px; background: linear-gradient(135deg, #4F2396 0%, #F27D4B 100%); font-size: 0.85rem;">
                        {{ strtoupper(substr($currentUser->name ?? 'A', 0, 1)) }}
                    </div>
                    <div class="d-none d-md-block text-start me-1">
                        <span class="d-block text-xs font-weight-bold text-dark lh-1">{{ $currentUser->name ?? 'Admin User' }}</span>
                        <span class="text-xxs text-muted">{{ optional($currentUser)->is_company ? 'Company' : 'Administrator' }}</span>
                    </div>
                    <i class="bi bi-chevron-down ms-1 text-muted text-xs me-1"></i>
                </a>

                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2 p-2" aria-labelledby="userProfileDropdown" style="border-radius: 14px; min-width: 180px;">
                    <li class="px-2 py-1 mb-1 border-bottom">
                        <span class="d-block text-xs font-weight-bold text-dark">{{ $currentUser->name ?? 'Admin User' }}</span>
                        <span class="text-xxs text-muted">{{ $currentUser->email ?? '' }}</span>
                    </li>
                    @if(Route::has('profile.show'))
                        <li><a class="dropdown-item rounded py-1.5 text-xs" href="{{ route('profile.show') }}"><i class="bi bi-person me-2"></i> Profile</a></li>
                    @endif
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item rounded py-1.5 text-xs text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i> Log Out
                            </button>
                        </form>
                    </li>
                </ul>
            </div>

        </div>
    </div>
</nav>

@push('scripts')
<script>
    $(document).ready(function() {
        function fetchHeaderNotifications() {
            if ($('#topHeaderNotificationsBell').length > 0) {
                $.ajax({
                    url: "{{ route('sidebar_notifications') }}",
                    method: "GET",
                    silent: true,
                    global: false,
                    success: function(response) {
                        if (response.success && response.total > 0) {
                            $('#topHeaderNotificationBadge').text(response.total).show();
                            $('#topHeaderNotificationCountHeader').text(response.total + ' New').show();
                            let itemsHtml = '';
                            response.items.forEach(function(item) {
                                let badgeColorClass = 'text-primary';
                                if (item.color === 'warning') badgeColorClass = 'text-warning';
                                if (item.color === 'danger') badgeColorClass = 'text-danger';
                                if (item.color === 'success') badgeColorClass = 'text-success';
                                if (item.color === 'info') badgeColorClass = 'text-info';

                                itemsHtml += `
                                    <li class="py-1">
                                        <a class="dropdown-item d-flex align-items-center p-2 rounded hover-bg-light" href="${item.url}" style="white-space: normal; transition: all 0.2s ease;">
                                            <div class="me-2.5 d-flex align-items-center justify-content-center bg-light ${badgeColorClass} rounded-circle shadow-xs me-2" style="width: 36px; height: 36px; flex-shrink: 0;">
                                                <i class="bi ${item.icon} fs-6"></i>
                                            </div>
                                            <div style="min-width: 0; flex: 1;">
                                                <div class="d-flex align-items-center justify-content-between mb-0.5">
                                                    <strong class="d-block text-dark text-truncate small mb-0" style="font-size: 0.8rem;">${item.title}</strong>
                                                    ${item.created_at ? `<span class="text-xxs text-muted ms-1" style="font-size: 0.65rem;">${item.created_at.substring(0, 10)}</span>` : ''}
                                                </div>
                                                <span class="text-muted d-block text-xs" style="line-height: 1.25;">${item.message}</span>
                                            </div>
                                        </a>
                                    </li>
                                `;
                            });
                            $('#topHeaderNotificationItems').html(itemsHtml);
                        } else {
                            $('#topHeaderNotificationBadge').hide();
                            $('#topHeaderNotificationCountHeader').hide();
                            $('#topHeaderNotificationItems').html(`
                                <li class="text-center text-muted py-3 small" id="topHeaderNoNotificationsItem">
                                    <i class="bi bi-check2-circle text-success fs-5 d-block mb-1"></i>
                                    No pending alerts
                                </li>
                            `);
                        }
                    }
                });
            }
        }

        fetchHeaderNotifications();
        // Periodically refresh notifications every 30 seconds
        setInterval(fetchHeaderNotifications, 30000);
    });
</script>
@endpush
