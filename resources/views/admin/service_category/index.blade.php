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

    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 text-dark font-weight-bold">Service Categories</h6>
                                <p class="text-muted text-xs mb-0">Manage multi-lingual service categories (English, Arabic, Urdu).</p>
                            </div>
                            <button type="button" class="btn btn-sm {{ in_array(2, $rolePermissions) ? '' : 'd-none' }}" style="background-color: #4F2396; color: white;" data-bs-toggle="modal"
                                data-bs-target="#addCategoryModal">
                                <i class="bi bi-plus-lg me-1"></i> Add New Category
                            </button>
                        </div>

                        <div class="card-body px-4 pt-3 pb-3">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped table-hover mb-0 align-middle text-sm" id="categoryTable">
                                    <thead class="thead-light">
                                        <tr>
                                            <th style="width: 8%">S.No</th>
                                            <th style="width: 12%">Image</th>
                                            <th style="width: 45%">Category Name (EN / AR / UR)</th>
                                            <th style="width: 20%">Created Date</th>
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

    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form id="addCategoryForm">
                @csrf
                <div class="modal-content">
                    <div class="modal-header {{ in_array('2', $rolePermissions ?? []) ? '' : 'd-none' }}">
                        <h5 class="modal-title font-weight-bold" id="addCategoryModalLabel">Add New Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        {{-- Image Upload --}}
                        <div class="mb-4 text-center">
                            <label for="category_img" class="d-inline-block position-relative">
                                <img id="category_imgPreview" src="{{ asset('assets/img/default.jpg') }}"
                                    class="rounded shadow-sm border"
                                    style="width: 180px; height: 130px; object-fit: contain; cursor: pointer;"
                                    title="Click to choose image">
                                <div class="mt-1"><small class="text-muted"><i class="bi bi-camera me-1"></i>Click image to upload</small></div>
                            </label>
                            <input type="file" name="category_image" id="category_img" accept="image/*" class="d-none">
                            @error('category_image')
                                <small class="text-danger d-block mt-2">{{ $message }}</small>
                            @enderror
                        </div>

                        {{-- Auto-Translate Helper Bar --}}
                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded border">
                            <small class="text-muted"><i class="bi bi-globe me-1"></i> Multi-language Support (EN, AR, UR)</small>
                            <button type="button" class="btn btn-xs btn-outline-primary mb-0" id="btnAutoTranslateAdd" title="Translate English name into Arabic & Urdu automatically">
                                <i class="bi bi-translate me-1"></i> Auto-Translate from English
                            </button>
                        </div>

                        {{-- English Name --}}
                        <div class="mb-3">
                            <label for="categoryName" class="form-label font-weight-bold text-dark text-xs">
                                Category Name (English) <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="name" id="categoryName" class="form-control"
                                placeholder="e.g. Plumbing, Electrical, Cleaning, AC Repair" required dir="ltr">
                        </div>

                        {{-- Arabic Name --}}
                        <div class="mb-3">
                            <label for="categoryNameAr" class="form-label font-weight-bold text-dark text-xs">
                                Category Name (Arabic - العربية)
                            </label>
                            <input type="text" name="name_ar" id="categoryNameAr" class="form-control"
                                placeholder="مثال: سباكة، كهرباء، تنظيف، تكييف" dir="rtl">
                        </div>

                        {{-- Urdu Name --}}
                        <div class="mb-3">
                            <label for="categoryNameUr" class="form-label font-weight-bold text-dark text-xs">
                                Category Name (Urdu - اردو)
                            </label>
                            <input type="text" name="name_ur" id="categoryNameUr" class="form-control"
                                placeholder="مثال: پلمبنگ، الیکٹریشن، صفائی، اے سی مرمت" dir="rtl">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn" id="addCategoryButton" style="background-color: #4F2396; color: white;">Save Category</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="addCategoryClose">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form id="editCategoryForm">
                @csrf
                @method('POST')
                <input type="hidden" id="editCategoryId" name="id">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title font-weight-bold" id="editCategoryModalLabel">Edit Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        {{-- Image Upload --}}
                        <div class="mb-4 text-center">
                            <label for="editcategory_img" class="d-inline-block position-relative">
                                <img id="editcategory_imgPreview" src="{{ asset('assets/img/default.jpg') }}"
                                    class="rounded shadow-sm border"
                                    style="width: 180px; height: 130px; object-fit: contain; cursor: pointer;"
                                    title="Click to choose image">
                                <div class="mt-1"><small class="text-muted"><i class="bi bi-camera me-1"></i>Click image to change</small></div>
                            </label>
                            <input type="file" name="category_image" id="editcategory_img" accept="image/*" class="d-none">
                            @error('category_image')
                                <small class="text-danger d-block mt-2">{{ $message }}</small>
                            @enderror
                        </div>

                        {{-- Auto-Translate Helper Bar --}}
                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded border">
                            <small class="text-muted"><i class="bi bi-globe me-1"></i> Multi-language Support (EN, AR, UR)</small>
                            <button type="button" class="btn btn-xs btn-outline-primary mb-0" id="btnAutoTranslateEdit" title="Translate English name into Arabic & Urdu automatically">
                                <i class="bi bi-translate me-1"></i> Auto-Translate from English
                            </button>
                        </div>

                        {{-- English Name --}}
                        <div class="mb-3">
                            <label for="editCategoryName" class="form-label font-weight-bold text-dark text-xs">
                                Category Name (English) <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="name" id="editCategoryName" class="form-control" required dir="ltr">
                        </div>

                        {{-- Arabic Name --}}
                        <div class="mb-3">
                            <label for="editCategoryNameAr" class="form-label font-weight-bold text-dark text-xs">
                                Category Name (Arabic - العربية)
                            </label>
                            <input type="text" name="name_ar" id="editCategoryNameAr" class="form-control" dir="rtl">
                        </div>

                        {{-- Urdu Name --}}
                        <div class="mb-3">
                            <label for="editCategoryNameUr" class="form-label font-weight-bold text-dark text-xs">
                                Category Name (Urdu - اردو)
                            </label>
                            <input type="text" name="name_ur" id="editCategoryNameUr" class="form-control" dir="rtl">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn" id="editCategoryButton" style="background-color: #4F2396; color: white;">Update Category</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title font-weight-bold" id="deleteCategoryModalLabel">Confirm Delete</h5>
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
