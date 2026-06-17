@extends('layouts.app')

@section('title', 'Edit Terms & Conditions')

<link rel="stylesheet" href="{{ asset('assets/css/admin/providers/create.css') }}">

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-md-10 col-lg-12">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Edit Terms &amp; Conditions</h6>
                            <a href="{{ route('admin.terms_conditions.index') }}" class="btn btn-sm btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                        </div>

                        @if ($errors->any())
                            <div class="alert alert-danger mx-3 mt-3">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="card-body">
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

                            <form action="{{ route('admin.terms_conditions.update', $terms_condition->id) }}" method="POST"
                                enctype="multipart/form-data">
                                @csrf
                                @method('PUT')

                                <div class="form-group mb-3">
                                    <label for="role" class="form-label fw-semibold">Role</label>
                                    <select name="role" id="role" class="form-control @error('role') is-invalid @enderror" required>
                                        <option value="" disabled>-- Select Role --</option>
                                        <option value="0" {{ old('role', $terms_condition->role) == '0' ? 'selected' : '' }}>User</option>
                                        <option value="1" {{ old('role', $terms_condition->role) == '1' ? 'selected' : '' }}>Provider</option>
                                        <option value="2" {{ old('role', $terms_condition->role) == '2' ? 'selected' : '' }}>Shop</option>
                                    </select>
                                    @error('role')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group mb-3">
                                    <label for="content" class="form-label fw-semibold">Content</label>
                                    <textarea name="content" id="content"
                                        class="form-control @error('content') is-invalid @enderror"
                                        rows="10">{{ old('content', $terms_condition->content) }}</textarea>
                                    @error('content')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <button type="submit" class="btn btn-success mt-2">
                                    <i class="bi bi-check-lg me-1"></i> Update Terms &amp; Conditions
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection

@push('scripts')
    {{-- CKEditor 5 CDN --}}
    <script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
    <script>
        ClassicEditor
            .create(document.querySelector('#content'), {
                toolbar: [
                    'heading', '|',
                    'bold', 'italic', 'underline', 'strikethrough', '|',
                    'bulletedList', 'numberedList', 'blockQuote', '|',
                    'link', '|',
                    'outdent', 'indent', '|',
                    'insertTable', 'horizontalLine', '|',
                    'undo', 'redo'
                ],
                placeholder: 'Enter Terms & Conditions content here...',
            })
            .catch(error => {
                console.error('CKEditor error:', error);
            });
    </script>
@endpush
