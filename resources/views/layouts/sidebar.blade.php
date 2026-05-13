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

<style>
    .active {
        color: #2FBECF !important;
    }
</style>
<aside class="sidenav bg-white navbar navbar-vertical navbar-expand-xs border-0 border-radius-xl my-3 fixed-start ms-4"
    id="sidenav-main">
    <div class="sidenav-header">
        <i class="fas fa-times p-3 cursor-pointer text-secondary opacity-5 position-absolute end-0 top-0 d-none d-xl-none"
            id="iconSidenav"></i>
        <a class="navbar-brand m-0" href="{{ url('/') }}">
            <img src="{{ $setting->logo ? asset('uploads/system_settings/' . $setting->logo) : asset('assets/img/logo.png') }}"
                width="30px" height="26px" class="navbar-brand-img h-100" alt="main_logo" />
            <span class="ms-1 font-weight-bold">{{ $setting->system_name }}</span>

        </a>
        </li>

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
                ];
                $isSystemOpen = in_array(Route::currentRouteName(), $systemRoutes);
            @endphp

            <a class="nav-link d-flex justify-content-between align-items-center {{ $isSystemOpen ? '' : 'collapsed' }} {{ $isSystemOpen ? 'active' : '' }}"
                data-bs-toggle="collapse" href="#SystemSettings" role="button"
                aria-expanded="{{ $isSystemOpen ? 'true' : 'false' }}" aria-controls="SystemSettings">
                <div class="d-flex align-items-center">
                    <div
                        class="icon icon-shape icon-sm text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="ni ni-settings text-dark text-sm opacity-10 {{ $isSystemOpen ? 'active' : '' }}"></i>
                    </div>
                    <span class="nav-link-text ms-1">System</span>
                </div>
                <i class="bi {{ $isSystemOpen ? 'bi-chevron-up' : 'bi-chevron-down' }}"></i>
            </a>

            <div class="collapse {{ $isSystemOpen ? 'show' : '' }}" id="SystemSettings">
                <ul class="nav flex-column ms-4">
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
                </ul>
            </div>
        </li>


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
                ];
                $isUserOpen = in_array(Route::currentRouteName(), $UserRoutes);
            @endphp
            <a class="nav-link d-flex justify-content-between align-items-center {{ $isUserOpen ? '' : 'collapsed' }} {{ $isUserOpen ? 'active' : '' }}"
                data-bs-toggle="collapse" href="#userManagement" role="button"
                aria-expanded="{{ $isUserOpen ? 'true' : 'false' }}" aria-controls="userManagement">
                <div class="d-flex align-items-center">
                    <div
                        class="icon icon-shape icon-sm text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="ni ni-circle-08 text-dark text-sm opacity-10 {{ $isUserOpen ? 'active' : '' }}"></i>
                    </div>
                    <span class="nav-link-text ms-1">User Management</span>
                </div>
                <i class="bi {{ $isUserOpen ? 'bi-chevron-up' : 'bi-chevron-down' }}"></i>
            </a>

            <div class="collapse {{ $isUserOpen ? 'show' : '' }}" id="userManagement">
                <ul class="nav flex-column ms-4">
                    <li class="nav-item {{ in_array('14', $rolePermissions) ? '' : 'd-none' }}"><a
                            class="nav-link {{ Route::currentRouteName() == 'users.index' ? 'active' : '' }}"
                            href="{{ route('users.index') }}">Users</a></li>
                    <li class="nav-item {{ in_array('18', $rolePermissions) ? '' : 'd-none' }}"><a
                            class="nav-link {{ Route::currentRouteName() == 'providers.index' ? 'active' : '' }}"
                            href="{{ route('providers.index') }}">Providers</a></li>
                    <li class="nav-item"><a
                            class="nav-link {{ in_array(Route::currentRouteName(), ['sellers.index', 'sellers.show', 'sellers.edit']) ? 'active' : '' }}"
                            href="{{ route('sellers.index') }}">Sellers</a></li>
                </ul>
            </div>
        </li>

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
            <a class="nav-link d-flex justify-content-between align-items-center {{ $isServiceManagementOpen ? '' : 'collapsed' }} {{ $isServiceManagementOpen ? 'active' : '' }}"
                data-bs-toggle="collapse" href="#JobsManagement" role="button"
                aria-expanded="{{ $isServiceManagementOpen ? 'true' : 'false' }}" aria-controls="JobsManagement">
                <div class="d-flex align-items-center">
                    <div
                        class="icon icon-shape icon-sm text-center me-2 d-flex align-items-center justify-content-center">
                        <i
                            class="ni ni-circle-08 text-dark text-sm opacity-10 {{ $isServiceManagementOpen ? 'active' : '' }}"></i>
                    </div>
                    <span class="nav-link-text ms-1">Jobs Management</span>
                </div>
                <i class="bi {{ $isServiceManagementOpen ? 'bi-chevron-up ' : 'bi-chevron-down' }}"></i>
            </a>

            <div class="collapse {{ $isServiceManagementOpen ? 'show ' : '' }}" id="JobsManagement">
                <ul class="nav flex-column ms-4">
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
            <a class="nav-link d-flex justify-content-between align-items-center {{ $isMarketplaceOpen ? '' : 'collapsed' }} {{ $isMarketplaceOpen ? 'active' : '' }}"
                data-bs-toggle="collapse" href="#MarketplaceManagement" role="button"
                aria-expanded="{{ $isMarketplaceOpen ? 'true' : 'false' }}" aria-controls="MarketplaceManagement">
                <div class="d-flex align-items-center">
                    <div
                        class="icon icon-shape icon-sm text-center me-2 d-flex align-items-center justify-content-center">
                        <i
                            class="ni ni-shop text-dark text-sm opacity-10 {{ $isMarketplaceOpen ? 'active' : '' }}"></i>
                    </div>
                    <span class="nav-link-text ms-1">Marketplace</span>
                </div>
                <i class="bi {{ $isMarketplaceOpen ? 'bi-chevron-up' : 'bi-chevron-down' }}"></i>
            </a>

            <div class="collapse {{ $isMarketplaceOpen ? 'show' : '' }}" id="MarketplaceManagement">
                <ul class="nav flex-column ms-4">
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

        {{-- profile --}}
        <li class="nav-item">
            <a class="nav-link {{ Route::currentRouteName() == 'profile.edit' ? 'active' : '' }}"
                href="{{ url('profile') }}">
                <div class="icon icon-shape icon-sm text-center me-2 d-flex align-items-center justify-content-center">
                    <i
                        class="ni ni-single-02 text-dark text-sm opacity-10 {{ Route::currentRouteName() == 'profile.edit' ? 'active' : '' }}"></i>
                </div>
                <span class="nav-link-text ms-1">Profile</span>
            </a>
        </li>

        {{-- chats  --}}
        <li class="nav-item">
            <a class="nav-link {{ Route::currentRouteName() == 'chats.index' ? 'active' : '' }}"
                href="{{ url('chats') }}">
                <div class="icon icon-shape icon-sm text-center me-2 d-flex align-items-center justify-content-center">
                    <i
                        class="ni ni-chat-round text-dark text-sm opacity-10 {{ Route::currentRouteName() == 'chats.index' ? 'active' : '' }}"></i>
                </div>
                <span class="nav-link-text ms-1 ">Chats</span>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link {{ Route::currentRouteName() == 'admin.privacy.index' ? 'active' : '' }}"
                href="{{ url('admin/privacy') }}">
                <div class="icon icon-shape icon-sm text-center me-2 d-flex align-items-center justify-content-center">
                    <i
                        class="ni ni-chat-round text-dark text-sm opacity-10 {{ Route::currentRouteName() == 'admin.privacy.index' ? 'active' : '' }}"></i>
                </div>
                <span class="nav-link-text ms-1 ">Privacy Policies</span>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link {{ Route::currentRouteName() == 'admin.app_versions.index' ? 'active' : '' }}"
                href="{{ route('admin.app_versions.index') }}">
                <div class="icon icon-shape icon-sm text-center me-2 d-flex align-items-center justify-content-center">
                    <i
                        class="ni ni-mobile-button text-dark text-sm opacity-10 {{ Route::currentRouteName() == 'admin.app_versions.index' ? 'active' : '' }}"></i>
                </div>
                <span class="nav-link-text ms-1">App Versions</span>
            </a>
        </li>
        {{--
        <li class="nav-item">
    <a class="nav-link" href="{{ route('admin.privacy.index') }}">
        <i class="nav-icon fas fa-user-shield"></i>
        <p>Privacy Policies</p>
    </a>
</li> --}}

        {{-- Logout --}}
        <li class="nav-item mt-3">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="nav-link bg-transparent border-0 d-flex align-items-center">
                    <div
                        class="icon icon-shape icon-sm text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="ni ni-user-run text-dark text-sm opacity-10"></i>
                    </div>
                    <span class="nav-link-text ms-1">Logout</span>
                </button>
            </form>
        </li>

        </ul>
    </div>
</aside>
