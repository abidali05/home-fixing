@extends('layouts.app')

@section('title', 'Orders')

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
                            <h6 class="mb-0">Orders</h6>
                        </div>

                        <div class="card-body px-4 pt-3 pb-3">
                            {{-- Tabs --}}
                            <ul class="nav nav-tabs mb-3" id="orderTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button
                                        class="nav-link {{ request('user_id') ? 'active' : (request('provider_id') ? '' : 'active') }}"
                                        id="user-tab" data-bs-toggle="tab" data-bs-target="#userOrders" type="button"
                                        role="tab">
                                        User Orders
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ request('provider_id') ? 'active' : '' }}" id="provider-tab"
                                        data-bs-toggle="tab" data-bs-target="#providerOrders" type="button" role="tab">
                                        Provider Orders
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content" id="orderTabsContent">
                                {{-- User Orders Tab --}}
                                <div class="tab-pane fade {{ request('user_id') ? 'show active' : (request('provider_id') ? '' : 'show active') }}"
                                    id="userOrders" role="tabpanel">
                                    <form method="GET" action="{{ route('orders.index') }}" class="row g-3 mb-3 mt-3">
                                        <div class="col-md-6">
                                            {{-- <label for="user_id" class="form-label">Select User</label> --}}
                                            <select name="user_id" id="user_id" class="form-select select2">
                                                <option value="">-- Select User --</option>
                                                @foreach ($users as $user)
                                                    <option value="{{ $user->id }}"
                                                        {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                                        {{ $user->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <button type="submit" class="btn w-100" style="background-color: #4F2396; color: white;">Search</button>
                                            @if (request('user_id'))
                                                <a href="{{ route('orders.index') }}"
                                                    class="btn btn-secondary ms-2">Clear</a>
                                            @endif
                                        </div>
                                    </form>

                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped text-sm w-100"
                                            id="userOrdersTable">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
                                                    <th>User</th>
                                                    <th>Provider</th>
                                                    <th>Order Date</th>
                                                    <th>Order Source </th>
                                                    <th>Price</th>
                                                    <th>Status</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>
                                </div>

                                {{-- Provider Orders Tab --}}
                                <div class="tab-pane fade {{ request('provider_id') ? 'show active' : '' }}"
                                    id="providerOrders" role="tabpanel">
                                    <form method="GET" action="{{ route('orders.index') }}" class="row g-3 mb-3 mt-3">
                                        <div class="col-md-6">
                                            {{-- <label for="provider_id" class="form-label">Select Provider</label> --}}
                                            <select name="provider_id" id="provider_id" class="form-select select2">
                                                <option value="">-- Select Provider --</option>
                                                @foreach ($providers as $provider)
                                                    <option value="{{ $provider->id }}"
                                                        {{ request('provider_id') == $provider->id ? 'selected' : '' }}>
                                                        {{ $provider->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <button type="submit" class="btn  w-100" style="background-color: #4F2396; color: white;">Search</button>
                                            @if (request('provider_id'))
                                                <a href="{{ route('orders.index') }}"
                                                    class="btn btn-secondary ms-2">Clear</a>
                                            @endif
                                        </div>
                                    </form>

                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped text-sm w-100"
                                            id="providerOrdersTable">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
                                                    <th>User</th>
                                                    <th>Provider</th>
                                                    <th>Order Date</th>
                                                    <th>Order Source </th>
                                                    <th>Price</th>
                                                    <th>Status</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>
                                </div>
                            </div>
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
            // Initialize empty DataTables
            const userOrdersTable = $('#userOrdersTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('orders.index') }}",
                    data: function(d) {
                        d.user_orders = true;
                        d.user_id = $('#user_id').val();
                    }
                },
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'user',
                        name: 'user'
                    },
                    {
                        data: 'provider',
                        name: 'provider'
                    },
                    {
                        data: 'created_at',
                        name: 'created_at'
                    },
                    {
                        data: 'source',
                        name: 'source'
                    },
                    {
                        data: 'price',
                        name: 'price'
                    },
                    {
                        data: 'status',
                        name: 'status'
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false
                    }
                ],
                language: {
                    emptyTable: "No orders found. Please apply filters to see results.",
                    zeroRecords: "No matching orders found",
                    processing: '<i class="fa fa-spinner fa-spin fa-3x fa-fw"></i>'
                }
            });

            const providerOrdersTable = $('#providerOrdersTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('orders.index') }}",
                    data: function(d) {
                        d.provider_orders = true;
                        d.provider_id = $('#provider_id').val();
                    }
                },
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'user',
                        name: 'user'
                    },
                    {
                        data: 'provider',
                        name: 'provider'
                    },
                    {
                        data: 'created_at',
                        name: 'created_at'
                    },
                    {
                        data: 'source',
                        name: 'source'
                    },
                    {
                        data: 'price',
                        name: 'price'
                    },
                    {
                        data: 'status',
                        name: 'status'
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false
                    }
                ],
                language: {
                    emptyTable: "No orders found. Please apply filters to see results.",
                    zeroRecords: "No matching orders found",
                    processing: '<i class="fa fa-spinner fa-spin fa-3x fa-fw"></i>'
                }
            });

            // Handle form submissions
            $('form').on('submit', function(e) {
                e.preventDefault();

                if ($(this).closest('.tab-pane').attr('id') === 'userOrders') {
                    userOrdersTable.ajax.reload();
                } else {
                    providerOrdersTable.ajax.reload();
                }
            });

            // Clear filters
            $('.btn-secondary').on('click', function() {
                $(this).closest('form').find('select').val('').trigger('change');
                if ($(this).closest('.tab-pane').attr('id') === 'userOrders') {
                    userOrdersTable.ajax.reload();
                } else {
                    providerOrdersTable.ajax.reload();
                }
                return false;
            });
        });
    </script>
@endpush
