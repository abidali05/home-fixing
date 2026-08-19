@php
    $setting = App\Models\Admin\SystemSettingModel::first();
    $rolePermissions = App\Models\Admin\RolePermissions::where('role_id', Auth::guard('admin')->user()->role)
        ->pluck('permission_id')
        ->toArray();
    $allowed_modules = App\Models\Admin\Permission::whereIn('id', $rolePermissions)
        ->pluck('module_name')
        ->unique()
        ->toArray();
@endphp

<aside class="sidenav bg-white navbar navbar-vertical navbar-expand-xs border-0 fixed-start" id="sidenav-main">
    <div class="sidenav-header">
        <i class="fas fa-times p-3 cursor-pointer text-secondary opacity-5 position-absolute end-0 top-0 d-none d-xl-none" id="iconSidenav"></i>
        <a class="navbar-brand m-0" href="{{ url('/') }}">
            <img src="{{ optional($setting)->logo ? asset('uploads/system_settings/' . $setting->logo) : asset('uploads/system_settings/Logo1.png') }}"
                class="navbar-brand-img me-2" alt="main_logo"/>
            {{-- <span class="ms-2 font-weight-bold">{{ optional($setting)->system_name ?? 'Home Fixing' }}</span> --}}
        </a>
        {{-- @if(!optional(Auth::guard('admin')->user())->is_company)
        <div class="dropdown me-2 position-static">
            <a href="#" class="position-relative d-inline-block" id="adminNotificationsBell" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                <i class="bi bi-bell-fill fs-5" style="color: rgba(255,255,255,0.85) !important;"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="adminNotificationBadge" style="font-size: 0.55rem; padding: 0.25em 0.4em; transform: translate(-30%, -30%) !important; display: none;">0</span>
            </a>
            <ul class="dropdown-menu py-2 px-3 shadow-lg border-0" aria-labelledby="adminNotificationsBell" id="adminNotificationDropdown">
                <li class="dropdown-header text-dark font-weight-bold p-0 mb-2 d-flex align-items-center justify-content-between">
                    <span>System Alerts</span>
                    <span class="badge bg-danger rounded-pill" id="adminNotificationCountHeader" style="display: none; font-size: 0.65rem;">0</span>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <div id="adminNotificationItems">
                    <li class="text-center text-muted py-2" id="noNotificationsItem">No pending alerts</li>
                </div>
            </ul>
        </div>
        @endif --}}
    </div>

    <div class="collapse navbar-collapse w-auto" id="sidenav-collapse-main">
        <ul class="navbar-nav">
        @if(optional(Auth::guard('admin')->user())->is_company)
            <li class="nav-item">
                <a class="nav-link {{ Route::currentRouteName() == 'dashboard' ? 'active' : '' }}" href="{{ route('dashboard') }}">
                    <div class="d-flex align-items-center">
                        <div class="icon">
                            <i class="ni ni-tv-2 text-primary text-sm opacity-10"></i>
                        </div>
                        <span class="nav-link-text">Dashboard</span>
                    </div>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ Route::currentRouteName() == 'companies.show' ? 'active' : '' }}" href="{{ route('companies.show', Auth::guard('admin')->user()->id) }}">
                    <div class="d-flex align-items-center">
                        <div class="icon">
                            <i class="bi bi-people-fill text-dark text-sm opacity-10"></i>
                        </div>
                        <span class="nav-link-text">My Providers</span>
                    </div>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ in_array(Route::currentRouteName(), ['orders.index', 'orders.details']) ? 'active' : '' }}" href="{{ route('orders.index') }}">
                    <div class="d-flex align-items-center">
                        <div class="icon">
                            <i class="bi bi-briefcase-fill text-dark text-sm opacity-10"></i>
                        </div>
                        <span class="nav-link-text">Orders</span>
                    </div>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ Route::currentRouteName() == 'chats.index' ? 'active' : '' }}" href="{{ route('chats.index') }}">
                    <div class="d-flex align-items-center">
                        <div class="icon">
                            <i class="ni ni-chat-round text-dark text-sm opacity-10"></i>
                        </div>
                        <span class="nav-link-text">Chats</span>
                    </div>
                </a>
            </li>
        @else
            <!-- System Menu -->
            <li class="nav-item {{ in_array('System', $allowed_modules) ? '' : 'd-none' }}">
                @php
                    $systemRoutes = [
                        'servicecategory.index',
                        'settings.index',
                        'roles.index',
                        'faqs.index',
                        'faqs.create',
                        'faqs.edit',
                        'support_items.index',
                        'support_items.create',
                        'support_items.edit',
                        'system_users.index',
                        'system_users.create',
                        'system_users.edit',
                        'admin.notifications.create',
                    ];
                    $isSystemOpen = in_array(Route::currentRouteName(), $systemRoutes);
                @endphp

                <a class="nav-link {{ $isSystemOpen ? '' : 'collapsed' }} {{ $isSystemOpen ? 'active' : '' }}"
                    data-bs-toggle="collapse" href="#SystemSettings" role="button"
                    aria-expanded="{{ $isSystemOpen ? 'true' : 'false' }}" aria-controls="SystemSettings">
                    <div class="d-flex align-items-center flex-grow-1">
                        <div class="icon">
                            <i class="ni ni-settings text-dark text-sm opacity-10"></i>
                        </div>
                        <span class="nav-link-text">System</span>
                    </div>
                    <i class="bi bi-chevron-down sidebar-chevron"></i>
                </a>

                <div class="collapse collapse-submenu {{ $isSystemOpen ? 'show' : '' }}" id="SystemSettings">
                    <ul class="nav flex-column">
                        <li class="nav-item {{ in_array('1', $rolePermissions) ? '' : 'd-none' }}">
                            <a class="nav-link {{ Route::currentRouteName() == 'servicecategory.index' ? 'active' : '' }}"
                                href="{{ route('servicecategory.index') }}">
                                Service Categories
                            </a>
                        </li>
                        <li class="nav-item {{ in_array('5', $rolePermissions) ? '' : 'd-none' }}">
                            <a class="nav-link {{ Route::currentRouteName() == 'settings.index' ? 'active' : '' }}"
                                href="{{ route('settings.index') }}">Settings</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ Route::currentRouteName() == 'admin.payments.index' ? 'active' : '' }}"
                                href="{{ route('admin.payments.index') }}">Payments & Transactions</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ Route::currentRouteName() == 'admin.withdrawals.index' ? 'active' : '' }}"
                                href="{{ route('admin.withdrawals.index') }}">Withdrawal Requests</a>
                        </li>
                        <li class="nav-item {{ in_array('6', $rolePermissions) ? '' : 'd-none' }}">
                            <a class="nav-link {{ Route::currentRouteName() == 'roles.index' ? 'active' : '' }}"
                                href="{{ route('roles.index') }}">Roles</a>
                        </li>
                        <li class="nav-item {{ in_array('10', $rolePermissions) ? '' : 'd-none' }}">
                            <a class="nav-link {{ Route::currentRouteName() == 'system_users.index' ? 'active' : '' }}"
                                href="{{ route('system_users.index') }}">System Users</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ in_array(Route::currentRouteName(), ['faqs.index', 'faqs.create', 'faqs.edit']) ? 'active' : '' }}"
                                href="{{ route('faqs.index') }}">FAQs</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ in_array(Route::currentRouteName(), ['support_items.index', 'support_items.create', 'support_items.edit']) ? 'active' : '' }}"
                                href="{{ route('support_items.index') }}">Support</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ Route::currentRouteName() == 'admin.notifications.create' ? 'active' : '' }}"
                                href="{{ route('admin.notifications.create') }}">Send Notifications</a>
                        </li>
                    </ul>
                </div>
            </li>

            <!-- User Management Menu -->
            <li class="nav-item {{ in_array('User Management', $allowed_modules) ? '' : 'd-none' }}">
                @php
                    $UserRoutes = [
                        'users.index',
                        'users.create',
                        'users.edit',
                        'providers.index',
                        'providers.show',
                        'providers.create',
                        'providers.edit',
                        'sellers.index',
                        'sellers.show',
                        'sellers.edit',
                        'account_active_requests.index',
                        'companies.index',
                        'companies.create',
                        'companies.edit',
                        'companies.show',
                    ];
                    $isUserOpen = in_array(Route::currentRouteName(), $UserRoutes);
                @endphp
                <a class="nav-link {{ $isUserOpen ? '' : 'collapsed' }} {{ $isUserOpen ? 'active' : '' }}"
                    data-bs-toggle="collapse" href="#userManagement" role="button"
                    aria-expanded="{{ $isUserOpen ? 'true' : 'false' }}" aria-controls="userManagement">
                    <div class="d-flex align-items-center flex-grow-1">
                        <div class="icon">
                            <i class="ni ni-circle-08 text-dark text-sm opacity-10"></i>
                        </div>
                        <span class="nav-link-text">User Management</span>
                    </div>
                    <i class="bi bi-chevron-down sidebar-chevron"></i>
                </a>

                <div class="collapse collapse-submenu {{ $isUserOpen ? 'show' : '' }}" id="userManagement">
                    <ul class="nav flex-column">
                        <li class="nav-item {{ in_array('14', $rolePermissions) ? '' : 'd-none' }}">
                            <a class="nav-link {{ Route::currentRouteName() == 'users.index' ? 'active' : '' }}"
                                href="{{ route('users.index') }}">Users</a>
                        </li>
                        <li class="nav-item {{ in_array('18', $rolePermissions) ? '' : 'd-none' }}">
                            <a class="nav-link {{ Route::currentRouteName() == 'providers.index' ? 'active' : '' }}"
                                href="{{ route('providers.index') }}">Providers</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ in_array(Route::currentRouteName(), ['sellers.index', 'sellers.show', 'sellers.edit']) ? 'active' : '' }}"
                                href="{{ route('sellers.index') }}">Sellers</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ Route::currentRouteName() == 'account_active_requests.index' ? 'active' : '' }}"
                                href="{{ route('account_active_requests.index') }}">Account Active Request</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ in_array(Route::currentRouteName(), ['companies.index', 'companies.create', 'companies.edit', 'companies.show']) ? 'active' : '' }}"
                                href="{{ route('companies.index') }}">Companies</a>
                        </li>
                    </ul>
                </div>
            </li>

            <!-- Jobs Management Menu -->
            <li class="nav-item {{ in_array('Jobs Managements', $allowed_modules) ? '' : 'd-none' }}">
                @php
                    $serviceManagementRoutes = [
                        'job_requests.index',
                        'job_requests.create',
                        'job_requests.edit',
                        'orders.index',
                        'orders.details',
                    ];
                    $isServiceManagementOpen = in_array(Route::currentRouteName(), $serviceManagementRoutes);
                @endphp
                <a class="nav-link {{ $isServiceManagementOpen ? '' : 'collapsed' }} {{ $isServiceManagementOpen ? 'active' : '' }}"
                    data-bs-toggle="collapse" href="#JobsManagement" role="button"
                    aria-expanded="{{ $isServiceManagementOpen ? 'true' : 'false' }}" aria-controls="JobsManagement">
                    <div class="d-flex align-items-center flex-grow-1">
                        <div class="icon">
                            <i class="bi bi-briefcase-fill text-dark text-sm opacity-10"></i>
                        </div>
                        <span class="nav-link-text">Jobs Management</span>
                    </div>
                    <i class="bi bi-chevron-down sidebar-chevron"></i>
                </a>

                <div class="collapse collapse-submenu {{ $isServiceManagementOpen ? 'show' : '' }}" id="JobsManagement">
                    <ul class="nav flex-column">
                        <li class="nav-item {{ in_array('22', $rolePermissions) ? '' : 'd-none' }}">
                            <a class="nav-link {{ Route::currentRouteName() == 'job_requests.index' ? 'active' : '' }}"
                                href="{{ route('job_requests.index') }}">
                                Job Requests
                            </a>
                        </li>
                        <li class="nav-item {{ in_array('30', $rolePermissions) ? '' : 'd-none' }}">
                            <a class="nav-link {{ Route::currentRouteName() == 'orders.index' || Route::currentRouteName() == 'orders.details' ? 'active' : '' }}"
                                href="{{ route('orders.index') }}">
                                Orders
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <!-- Marketplace Menu -->
            <li class="nav-item">
                @php
                    $marketplaceRoutes = [
                        'marketplace.orders.index',
                        'marketplace.orders.show',
                        'marketplace.products.index',
                        'marketplace.products.create',
                        'marketplace.products.show',
                        'marketplace.products.edit',
                        'marketplace.campaigns.index',
                        'marketplace.campaigns.create',
                        'marketplace.campaigns.edit',
                    ];
                    $isMarketplaceOpen = in_array(Route::currentRouteName(), $marketplaceRoutes);
                @endphp
                <a class="nav-link {{ $isMarketplaceOpen ? '' : 'collapsed' }} {{ $isMarketplaceOpen ? 'active' : '' }}"
                    data-bs-toggle="collapse" href="#MarketplaceManagement" role="button"
                    aria-expanded="{{ $isMarketplaceOpen ? 'true' : 'false' }}" aria-controls="MarketplaceManagement">
                    <div class="d-flex align-items-center flex-grow-1">
                        <div class="icon">
                            <i class="ni ni-shop text-dark text-sm opacity-10"></i>
                        </div>
                        <span class="nav-link-text">Marketplace</span>
                    </div>
                    <i class="bi bi-chevron-down sidebar-chevron"></i>
                </a>

                <div class="collapse collapse-submenu {{ $isMarketplaceOpen ? 'show' : '' }}" id="MarketplaceManagement">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link {{ in_array(Route::currentRouteName(), ['marketplace.orders.index', 'marketplace.orders.show']) ? 'active' : '' }}"
                                href="{{ route('marketplace.orders.index') }}">Order Management</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ in_array(Route::currentRouteName(), ['marketplace.products.index', 'marketplace.products.create', 'marketplace.products.show', 'marketplace.products.edit']) ? 'active' : '' }}"
                                href="{{ route('marketplace.products.index') }}">Product Management</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ in_array(Route::currentRouteName(), ['marketplace.campaigns.index', 'marketplace.campaigns.create', 'marketplace.campaigns.edit']) ? 'active' : '' }}"
                                href="{{ route('marketplace.campaigns.index') }}">Campaign Management</a>
                        </li>
                    </ul>
                </div>
            </li>

            <!-- Direct Nav Items -->
            <li class="nav-item">
                <a class="nav-link {{ Route::currentRouteName() == 'profile.edit' ? 'active' : '' }}"
                    href="{{ url('profile') }}">
                    <div class="d-flex align-items-center">
                        <div class="icon">
                            <i class="ni ni-single-02 text-dark text-sm opacity-10"></i>
                        </div>
                        <span class="nav-link-text">Profile</span>
                    </div>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link {{ Route::currentRouteName() == 'chats.index' ? 'active' : '' }}"
                    href="{{ url('chats') }}">
                    <div class="d-flex align-items-center">
                        <div class="icon">
                            <i class="ni ni-chat-round text-dark text-sm opacity-10"></i>
                        </div>
                        <span class="nav-link-text">Chats</span>
                    </div>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link {{ Route::currentRouteName() == 'admin.privacy.index' ? 'active' : '' }}"
                    href="{{ url('admin/privacy') }}">
                    <div class="d-flex align-items-center">
                        <div class="icon">
                            <i class="ni ni-chat-round text-dark text-sm opacity-10"></i>
                        </div>
                        <span class="nav-link-text">Privacy Policies</span>
                    </div>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link {{ in_array(Route::currentRouteName(), ['admin.terms_conditions.index', 'admin.terms_conditions.create', 'admin.terms_conditions.edit']) ? 'active' : '' }}"
                    href="{{ route('admin.terms_conditions.index') }}">
                    <div class="d-flex align-items-center">
                        <div class="icon">
                            <i class="ni ni-single-copy-04 text-dark text-sm opacity-10"></i>
                        </div>
                        <span class="nav-link-text">Terms &amp; Conditions</span>
                    </div>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link {{ Route::currentRouteName() == 'admin.app_versions.index' ? 'active' : '' }}"
                    href="{{ route('admin.app_versions.index') }}">
                    <div class="d-flex align-items-center">
                        <div class="icon">
                            <i class="ni ni-mobile-button text-dark text-sm opacity-10"></i>
                        </div>
                        <span class="nav-link-text">App Versions</span>
                    </div>
                </a>
            </li>
        @endif
        </ul>
    </div>
</aside>

@push('scripts')
<script>
    $(document).ready(function() {
        if ($('#adminNotificationsBell').length > 0) {
            $.ajax({
                url: "{{ route('sidebar_notifications') }}",
                method: "GET",
                success: function(response) {
                    if (response.success && response.total > 0) {
                        $('#adminNotificationBadge').text(response.total).show();
                        $('#adminNotificationCountHeader').text(response.total).show();
                        let itemsHtml = '';
                        response.items.forEach(function(item) {
                            itemsHtml += `
                                <li class="py-1">
                                    <a class="dropdown-item d-flex align-items-center p-2 rounded" href="${item.url}" style="white-space: normal;">
                                        <div class="me-2 d-flex align-items-center justify-content-center bg-light text-${item.color} rounded-circle" style="width: 32px; height: 32px; flex-shrink: 0;">
                                            <i class="bi ${item.icon} fs-6"></i>
                                        </div>
                                        <div style="min-width: 0; flex: 1;">
                                            <strong class="d-block text-dark text-truncate" style="font-size: 0.78rem;">${item.title}</strong>
                                            <span class="text-muted d-block" style="font-size: 0.72rem; line-height: 1.2;">${item.message}</span>
                                        </div>
                                    </a>
                                </li>
                            `;
                        });
                        $('#adminNotificationItems').html(itemsHtml);
                    }
                }
            });
        }
    });
</script>
@endpush
