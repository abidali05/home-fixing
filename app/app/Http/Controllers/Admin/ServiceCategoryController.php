<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use App\Http\Controllers\Controller;
use App\Models\Admin\RolePermissions;
use App\Models\Admin\ServiceCategory;
use Illuminate\Support\Facades\Validator;
use App\Models\Admin\ServiceCategoryModel;

class ServiceCategoryController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = ServiceCategoryModel::orderBy('id', 'desc')->get();
            $user = auth()->user();
            // Get user's role permissions
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
                ->editColumn('created_at', function ($row) {
                    return $row->created_at->format('d/m/Y');
                })
                ->addColumn('action', function ($row) use ($rolePermissions) {
                    $actionBtns = '';

                    if (in_array(3, $rolePermissions)) {
                        $actionBtns .= '<button class="btn btn-sm btn-link text-primary me-2 editCategoryBtn"
                            data-id="' . $row->id . '"
                            data-name="' . $row->name . '"
                            data-path="' . ($row->path ? asset('uploads/service_category/' . $row->path) : asset('assets/img/default.jpg')) . '">
                            <i class="bi bi-pencil-square"></i>
                        </button>';
                    }
                    if (in_array(4, $rolePermissions)) {
                        $actionBtns .= '<button class="btn btn-sm btn-link text-danger deleteCategoryBtn"
                            data-id="' . $row->id . '">
                            <i class="bi bi-trash"></i>
                        </button>';
                    }
                    return $actionBtns;
                })
                ->rawColumns(['action', 'path'])
                ->make(true);
        }

        $rolePermissions = auth()->user()->permissions ?? []; 

        return view('admin.service_category.index', compact('rolePermissions'));
    }


    public function store(Request $request)
    {

        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'category_image' => 'required|image|mimes:jpeg,png,jpg|max:8192',
        ]);

        $service_category = new ServiceCategoryModel();
        $service_category->name = $request->name;
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
            'category_image' => 'nullable|image|mimes:jpeg,png,jpg|max:8192',
        ]);

        $category = ServiceCategoryModel::findOrFail($id);
        if ($request->hasFile('category_image')) {
            $file = $request->file('category_image');
            $extension = $file->getClientOriginalExtension();
            $filename = uniqid('', true) . '.' . $extension;
            $file->move(public_path('uploads/service_category/'), $filename);
            if ($category->path && file_exists(public_path('uploads/service_category/' . $category->path))) {

                unlink(public_path('uploads/service_category/' . $category->path));
            }
            $category->path = $filename;
        }
        $category->name = $request->name;
        $category->save();

        return response()->json(['status' => 200, 'message' => 'Category updated successfully.'], 200);
    }

    public function destroy($id)
    {
        $category = ServiceCategoryModel::findOrFail($id);
        unlink(public_path('uploads/service_category/' . $category->path));
        $category->delete();

        return response()->json(['status' => 200, 'message' => 'Category deleted successfully.']);
    }
}
