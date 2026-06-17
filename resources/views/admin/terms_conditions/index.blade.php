@extends('layouts.app')

@section('title', 'Terms & Conditions')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Terms &amp; Conditions</h6>
                            <a href="{{ route('admin.terms_conditions.create') }}" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus-lg me-1"></i> Add New Terms &amp; Conditions
                            </a>
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

                                <table class="table table-bordered table-striped table-hover mb-0 align-middle text-sm">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Role</th>
                                            <th>Content Preview</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($termsConditions as $terms)
                                            <tr>
                                                <td>{{ $terms->id }}</td>
                                                <td>
                                                    @if ($terms->role == '0')
                                                        <span class="badge bg-primary">User</span>
                                                    @elseif ($terms->role == '1')
                                                        <span class="badge bg-success">Provider</span>
                                                    @elseif ($terms->role == '2')
                                                        <span class="badge bg-warning text-dark">Shop</span>
                                                    @else
                                                        <span class="badge bg-secondary">Unknown</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div style="max-height:60px; overflow:hidden;">
                                                        {!! strip_tags(Str::limit($terms->content, 120)) !!}
                                                    </div>
                                                </td>
                                                <td>
                                                    <a href="{{ route('admin.terms_conditions.edit', $terms->id) }}"
                                                        class="btn btn-warning btn-sm">
                                                        <i class="bi bi-pencil-square"></i> Edit
                                                    </a>
                                                    <form action="{{ route('admin.terms_conditions.destroy', $terms->id) }}"
                                                        method="POST" style="display:inline;"
                                                        onsubmit="return confirm('Are you sure you want to delete this entry?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-danger btn-sm">
                                                            <i class="bi bi-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-4">
                                                    No Terms &amp; Conditions found. <a href="{{ route('admin.terms_conditions.create') }}">Add one now</a>.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
