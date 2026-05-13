@extends('layouts.app')

@section('title', 'Edit Role')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0">Edit Role</h6>
                </div>
                <div class="card-body">

                   
                    @if (session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    @error('permissions')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror

                    <form action="{{ route('roles.update', $role->id) }}" method="POST">
                        @csrf
                        @method('POST')

                       
                        <div class="mb-3">
                            <label for="role_name" class="form-label fw-semibold">Role Name</label>
                            <input type="text" name="name" id="role_name"
                                class="form-control form-control-lg @error('name') is-invalid @enderror"
                                value="{{ old('name', $role->name) }}" required>
                            @error('name')
                                <span class="invalid-feedback d-block">{{ $message }}</span>
                            @enderror
                        </div>

                      
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Assign Permissions</label>
                            <div class="row">
                                @php
                                    $selectedPermissions = old(
                                        'permissions',
                                        $role->permissions->pluck('id')->toArray(),
                                    );
                                @endphp

                                @foreach ($permissions->groupBy('module_name') as $module => $perms)
                                    @php
                                        $moduleSlug = Str::slug($module);
                                        $hasSelected = $perms
                                            ->pluck('id')
                                            ->intersect($selectedPermissions)
                                            ->isNotEmpty();
                                    @endphp

                                    <div class="col-md-4 mb-3">
                                     
                                        <div class="form-check form-switch">
                                            <input class="form-check-input module-toggle" type="checkbox"
                                                id="module_{{ $moduleSlug }}" data-bs-toggle="collapse"
                                                data-bs-target="#permGroup_{{ $moduleSlug }}"
                                                {{ $hasSelected ? 'checked' : '' }}>
                                            <label class="form-check-label fw-bold text-primary"
                                                for="module_{{ $moduleSlug }}">
                                                {{ ucfirst($module) }}
                                            </label>
                                            <small class="select_all text-decoration-underline text-info ms-2"
                                                style="cursor: pointer;" data-module="{{ $moduleSlug }}">(Select
                                                All)</small>
                                        </div>

                                      
                                        <div class="collapse ms-4 mt-2 {{ $hasSelected ? 'show' : '' }}"
                                            id="permGroup_{{ $moduleSlug }}">
                                            @foreach ($perms as $perm)
                                                <div class="form-check">
                                                    <input class="form-check-input {{ $moduleSlug }}_permissions" type="checkbox" name="permissions[]"
                                                        value="{{ $perm->id }}" id="perm_{{ $perm->id }}"
                                                        {{ in_array($perm->id, $selectedPermissions) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="perm_{{ $perm->id }}">
                                                        {{ $perm->name }}
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                     
                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-primary">Update Role</button>
                            <a href="{{ route('roles.index') }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
@endsection

@push('scripts')
    <script src="{{ asset('customjs/roles/create.js') }}"></script>
@endpush
