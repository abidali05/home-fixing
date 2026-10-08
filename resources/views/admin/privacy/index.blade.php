@extends('layouts.app')

@section('title', 'Privacy Policies')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Privacy Policies</h6>
                            <a href="{{ route('admin.privacy.create') }}" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus-lg me-1"></i> Add New Privacy Policy
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
                                            <th>Content</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($privacyPolicies as $policy)
                                            <tr>
                                                <td>{{ $policy->id }}</td>
                                                <td>
                                                    @if ($policy->role == '0')
                                                        Customer
                                                    @elseif ($policy->role == '1')
                                                        Provider
                                                    @elseif ($policy->role == '2')
                                                        Shop
                                                    @else
                                                        Unknown
                                                    @endif
                                                </td>
                                                <td>{{ $policy->content }}</td>
                                                <td>
                                                    <a href="{{ route('admin.privacy.edit', $policy->id) }}" class="btn btn-warning btn-sm">Edit</a>
                                                    <form action="{{ route('admin.privacy.destroy', $policy->id) }}" method="POST" style="display:inline;">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
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
