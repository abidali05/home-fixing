@extends('layouts.app')

@section('title', 'Service Categories')

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
    {{-- <link rel="stylesheet" href="{{ asset('assets/css/service_category/index.css') }}"> --}}
    <main class="main-content position-relative border-radius-lg">

        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header pb-0">
                            <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Service Categories</h6>
                                <button type="button" class="btn btn-sm {{ in_array(2, $rolePermissions) ? '' : 'd-none' }}" style="background-color: #2BBDCE; color: white;" data-bs-toggle="modal"
                                    data-bs-target="#addCategoryModal">
                                    <i class="bi bi-plus-lg me-1"></i> Add New Category
                                </button>
                            </div>


                        </div>
                        <div class="card-body px-4 pt-3 pb-3">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped table-hover mb-0 align-middle text-sm"
                                    id="categoryTable">
                                    <thead class="thead-light">
                                        <tr>
                                            <th style="width: 10%">S.No</th>
                                            <th>Image</th>
                                            <th>Name</th>
                                            <th>Created Date</th>
                                            <th style="width: 15%">Action</th>
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


    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg ">
            <form id="addCategoryForm">
                @csrf
                <div class="modal-content">
                    <div class="modal-header {{ in_array('2', $rolePermissions ?? []) ? '' : 'd-none' }}">
                        <h5 class="modal-title" id="addCategoryModalLabel">Add New Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">


                        <div class="mb-4 text-end me-2">
                            <label for="category_img" class="d-inline-block">
                                <img id="category_imgPreview" src="{{ asset('assets/img/default.jpg') }}"
                                    class="rounded shadow"
                                    style="width: 200px; height: 150px; object-fit: contain; cursor: pointer;">
                            </label>
                            <input type="file" name="category_image" id="category_img" accept="image/*" class="d-none">
                            @error('category_image')
                                <small class="text-danger d-block mt-2">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="categoryName" class="form-label">Category Name</label>
                            <input type="text" name="name" id="categoryName" class="form-control form-control-lg"
                                required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn " id="addCategoryButton" style="background-color: #2BBDCE; color: white;">Save Category</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                            id="addCategoryClose">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form id="editCategoryForm">
                @csrf
                @method('POST')
                <input type="hidden" id="editCategoryId" name="id">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editCategoryModalLabel">Edit Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-4 text-end me-2">
                            <label for="editcategory_img" class="d-inline-block">
                                <img id="editcategory_imgPreview" src="{{ asset('assets/img/default.jpg') }}"
                                    class="rounded shadow"
                                    style="width: 200px; height: 150px; object-fit: contain; cursor: pointer;">
                            </label>
                            <input type="file" name="category_image" id="editcategory_img" accept="image/*"
                                class="d-none">
                            @error('category_image')
                                <small class="text-danger d-block mt-2">{{ $message }}</small>
                            @enderror
                        </div>


                        <div class="mb-3">
                            <label for="editCategoryName" class="form-label">Category Name</label>
                            <input type="text" name="name" id="editCategoryName"
                                class="form-control form-control-lg" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn" id="editCategoryButton" style="background-color: #2BBDCE; color: white;">Update Category</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteCategoryModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this category?
                    <input type="hidden" id="deleteCategoryId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmDeleteCategoryBtn" class="btn btn-danger">Delete</button>
                </div>
            </div>
        </div>
    </div>

@endsection


@push('scripts')
    <script>
        const categoryDataUrl = "{{ route('servicecategory.index') }}";
    </script>
    <script src="{{ asset('customjs/service_category/index.js') }}"></script>
@endpush
