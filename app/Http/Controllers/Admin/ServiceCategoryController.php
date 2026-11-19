<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use App\Http\Controllers\Controller;
use App\Models\Admin\RolePermissions;
use App\Models\Admin\ServiceCategoryModel;

class ServiceCategoryController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = ServiceCategoryModel::orderBy('id', 'desc')->get();
            $user = auth()->user();
            $rolePermissions = [];
            if ($user) {
                $rolePermissions = RolePermissions::where('role_id', $user->role)
                    ->pluck('permission_id')
                    ->toArray();
            }

            return DataTables::of($data)
                ->addIndexColumn()
                ->editColumn('path', function ($row) {
                    $imagePath = $row->path
                        ? asset('uploads/service_category/' . $row->path)
                        : asset('assets/img/default.jpg');
                    return $imagePath;
                })
                ->editColumn('name', function ($row) {
                    $html = '<div class="fw-bold text-dark">' . htmlspecialchars((string) $row->name) . '</div>';
                    $subNames = [];
                    if (!empty($row->name_ar)) {
                        $subNames[] = '<span class="badge bg-light text-primary border" dir="rtl">AR: ' . htmlspecialchars($row->name_ar) . '</span>';
                    }
                    if (!empty($row->name_ur)) {
                        $subNames[] = '<span class="badge bg-light text-success border" dir="rtl">UR: ' . htmlspecialchars($row->name_ur) . '</span>';
                    }
                    if (!empty($subNames)) {
                        $html .= '<div class="mt-1 d-flex gap-1 flex-wrap">' . implode(' ', $subNames) . '</div>';
                    }
                    return $html;
                })
                ->editColumn('created_at', function ($row) {
                    return $row->created_at ? $row->created_at->format('d/m/Y') : 'N/A';
                })
                ->addColumn('action', function ($row) use ($rolePermissions) {
                    $actionBtns = '';

                    if (in_array(3, $rolePermissions)) {
                        $actionBtns .= '<button class="btn btn-sm btn-link text-primary me-2 editCategoryBtn"
                            data-id="' . $row->id . '"
                            data-name="' . htmlspecialchars((string) $row->name, ENT_QUOTES) . '"
                            data-name_ar="' . htmlspecialchars((string) ($row->name_ar ?? ''), ENT_QUOTES) . '"
                            data-name_ur="' . htmlspecialchars((string) ($row->name_ur ?? ''), ENT_QUOTES) . '"
                            data-path="' . ($row->path ? asset('uploads/service_category/' . $row->path) : asset('assets/img/default.jpg')) . '"
                            title="Edit Category">
                            <i class="bi bi-pencil-square fs-6"></i>
                        </button>';
                    }
                    if (in_array(4, $rolePermissions)) {
                        $actionBtns .= '<button class="btn btn-sm btn-link text-danger deleteCategoryBtn"
                            data-id="' . $row->id . '"
                            title="Delete Category">
                            <i class="bi bi-trash fs-6"></i>
                        </button>';
                    }
                    return $actionBtns;
                })
                ->rawColumns(['action', 'path', 'name'])
                ->make(true);
        }

        $rolePermissions = auth()->user()->permissions ?? [];

        return view('admin.service_category.index', compact('rolePermissions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'name_ar' => 'nullable|string|max:255',
            'name_ur' => 'nullable|string|max:255',
            'category_image' => 'required|image|mimes:jpeg,png,jpg,svg,webp|max:8192',
        ]);

        $service_category = new ServiceCategoryModel();
        $service_category->name = trim((string) $request->name);
        $service_category->name_ar = $request->filled('name_ar') ? trim((string) $request->name_ar) : null;
        $service_category->name_ur = $request->filled('name_ur') ? trim((string) $request->name_ur) : null;

        if ($request->hasFile('category_image')) {
            $file = $request->file('category_image');
            $extension = $file->getClientOriginalExtension();
            $filename = uniqid('', true) . '.' . $extension;
            $file->move(public_path('uploads/service_category/'), $filename);
            $service_category->path = $filename;
        }

        $service_category->save();

        return response()->json(['status' => 200, 'message' => 'Category created successfully.'], 200);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $id,
            'name_ar' => 'nullable|string|max:255',
            'name_ur' => 'nullable|string|max:255',
            'category_image' => 'nullable|image|mimes:jpeg,png,jpg,svg,webp|max:8192',
        ]);

        $category = ServiceCategoryModel::findOrFail($id);
        $category->name = trim((string) $request->name);
        $category->name_ar = $request->filled('name_ar') ? trim((string) $request->name_ar) : null;
        $category->name_ur = $request->filled('name_ur') ? trim((string) $request->name_ur) : null;

        if ($request->hasFile('category_image')) {
            $file = $request->file('category_image');
            $extension = $file->getClientOriginalExtension();
            $filename = uniqid('', true) . '.' . $extension;
            $file->move(public_path('uploads/service_category/'), $filename);

            if ($category->path && file_exists(public_path('uploads/service_category/' . $category->path))) {
                @unlink(public_path('uploads/service_category/' . $category->path));
            }
            $category->path = $filename;
        }

        $category->save();

        return response()->json(['status' => 200, 'message' => 'Category updated successfully.'], 200);
    }

    public function destroy($id)
    {
        $category = ServiceCategoryModel::findOrFail($id);
        if ($category->path && file_exists(public_path('uploads/service_category/' . $category->path))) {
            @unlink(public_path('uploads/service_category/' . $category->path));
        }
        $category->delete();

        return response()->json(['status' => 200, 'message' => 'Category deleted successfully.']);
    }
}
