@extends('layouts.app')

@section('title', 'Support Items')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Support Items</h6>
                    <a href="{{ route('support_items.create') }}" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> Add Support Item
                    </a>
                </div>
                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover text-sm">
                            <thead>
                                <tr>
                                    <th style="width: 5%">S.No</th>
                                    <th>Icon</th>
                                    <th>Title</th>
                                    <th>Value</th>
                                    <th>Type</th>
                                    <th>Sort</th>
                                    <th>Status</th>
                                    <th style="width: 16%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($items as $item)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>
                                            @if (!empty($item->icon))
                                                <img src="{{ asset('uploads/support_items/' . $item->icon) }}"
                                                    alt="Support Icon"
                                                    style="width: 36px; height: 36px; object-fit: contain;">
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $item->title }}</td>
                                        <td>{{ $item->value }}</td>
                                        <td>{{ $item->type ?? '-' }}</td>
                                        <td>{{ $item->sort_order }}</td>
                                        <td>
                                            @if ($item->is_active)
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-secondary">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('support_items.edit', $item->id) }}"
                                                class="btn btn-sm btn-link text-primary">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <form action="{{ route('support_items.delete', $item->id) }}" method="POST"
                                                class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-link text-danger"
                                                    onclick="return confirm('Delete this support item?')">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center">No support items found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
