@extends('layouts.app')

@section('title', 'Edit Privacy Policy')

<link rel="stylesheet" href="{{ asset('assets/css/admin/providers/create.css') }}">

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-md-10 col-lg-12">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Edit Privacy Policy</h6>
                            <a href="{{ route('admin.privacy.index') }}" class="btn btn-sm btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                        </div>
                        @if ($errors->any())
                            <div class="alert alert-danger">
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

                            <form action="{{ route('admin.privacy.update', $privacy->id) }}" method="POST"
                                enctype="multipart/form-data">
                                @csrf
                                @method('PUT')
                                <div class="form-group">
                                    <label for="role">Role</label>
                                    <select name="role" id="role" class="form-control" required>
                                        <option value="0" {{ old('role', $privacy->role ?? '') == '0' ? 'selected' : '' }}>User</option>
                                        <option value="1" {{ old('role', $privacy->role ?? '') == '1' ? 'selected' : '' }}>Provider</option>
                                        <option value="2" {{ old('role', $privacy->role ?? '') == '2' ? 'selected' : '' }}>Shop</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="content">Content</label>
                                    <textarea name="content" id="content" class="form-control" rows="10" required>{{ old('content', $privacy->content) }}</textarea>
                                </div>

                                <button type="submit" class="btn btn-success mt-3">Update</button>
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
                placeholder: 'Enter Privacy Policy content here...',
            })
            .catch(error => {
                console.error('CKEditor error:', error);
            });
    </script>
@endpush
