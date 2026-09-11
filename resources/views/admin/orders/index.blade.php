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
                        <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 text-dark font-weight-bold">Orders Management</h6>
                                <p class="text-muted text-xs mb-0">View, filter, and download receipts for customer & provider service orders.</p>
                            </div>
                        </div>

                        <div class="card-body px-4 pt-3 pb-3">
                            {{-- Unified Filter Form --}}
                            <form id="ordersFilterForm" class="row g-3 mb-4 p-3 bg-light border-radius-lg align-items-end">
                                <div class="col-md-9">
                                    <label for="provider_id" class="form-label text-dark font-weight-bold text-xs">Filter by Service Provider</label>
                                    <select name="provider_id" id="provider_id" class="form-select select2">
                                        <option value="">-- All Service Providers --</option>
                                        @foreach ($providers as $provider)
                                            <option value="{{ $provider->id }}" {{ request('provider_id') == $provider->id ? 'selected' : '' }}>
                                                {{ $provider->name }} ({{ $provider->phone ?? 'ID #' . $provider->id }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary w-100 mb-0" style="background-color: #4F2396; border-color: #4F2396;">
                                        <i class="bi bi-funnel me-1"></i> Filter
                                    </button>
                                    <button type="button" id="resetOrdersFilter" class="btn btn-outline-secondary mb-0">
                                        Clear
                                    </button>
                                </div>
                            </form>

                            <div class="table-responsive">
                                <table class="table table-bordered table-striped text-sm w-100 align-middle" id="ordersTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 6%">S.No</th>
                                            <th>Customer</th>
                                            <th>Provider</th>
                                            <th>Order Date</th>
                                            <th>Order Source</th>
                                            <th>Price</th>
                                            <th>Status</th>
                                            <th style="width: 15%" class="text-center">Action</th>
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
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            const ordersTable = $('#ordersTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('orders.index') }}",
                    data: function(d) {
                        d.provider_id = $('#provider_id').val();
                    }
                },
                columns: [
                    { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                    { data: 'user', name: 'user' },
                    { data: 'provider', name: 'provider' },
                    { data: 'created_at', name: 'created_at' },
                    { data: 'source', name: 'source' },
                    { data: 'price', name: 'price' },
                    { data: 'status', name: 'status' },
                    { data: 'action', name: 'action', orderable: false, searchable: false }
                ],
                language: {
                    emptyTable: "No orders found.",
                    zeroRecords: "No matching orders found",
                    processing: '<i class="fa fa-spinner fa-spin fa-2x fa-fw"></i>'
                }
            });

            $('#ordersFilterForm').on('submit', function(e) {
                e.preventDefault();
                ordersTable.ajax.reload();
            });

            $('#provider_id').on('change', function() {
                ordersTable.ajax.reload();
            });

            $('#resetOrdersFilter').on('click', function() {
                $('#provider_id').val('').trigger('change.select2');
                ordersTable.ajax.reload();
            });
        });
    </script>
@endpush
