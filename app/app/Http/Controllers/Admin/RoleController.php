<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin\Role;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Admin\Permission;
use App\Models\Admin\RolePermissions;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class RoleController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Role::where('id', '!=', 1)->orderBy('id', 'desc')->get();
            $user = auth()->user();
            // Get user's role permissions
            $rolePermissions = [];
            if ($user) {
                $rolePermissions = \App\Models\Admin\RolePermissions::where('role_id', $user->role)
                    ->pluck('permission_id')
                    ->toArray();
            }
            return DataTables::of($data)
                ->addIndexColumn()
                ->editColumn('created_at', function ($row) {
                    return $row->created_at->format('d/m/Y');
                })
                ->addColumn('action', function ($row) use ($rolePermissions) {
                    $editUrl = route('roles.edit', $row->id);
                    $actionBtns = '';
                    // Permission 8: Edit, Permission 9: Delete
                    if (in_array(8, $rolePermissions)) {
                        $actionBtns .= '<a href="' . $editUrl . '" class="text-primary me-3"><i class="bi bi-pencil-square"></i></a>';
                    }
                    if (in_array(9, $rolePermissions)) {
                        $actionBtns .= '<a href="javascript:void(0);" class="text-danger deleteRoleBtn" data-id="' . $row->id . '"><i class="bi bi-trash-fill"></i></a>';
                    }
                    return $actionBtns;
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('admin.roles.index');
    }

    public function create(Request $request)
    {
        $permissions = Permission::all();
        return view('admin.roles.create', compact('permissions'));
    }


    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:roles,name',
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        DB::beginTransaction();

        try {
            $role = Role::create(['name' => $request->name]);

            foreach ($request->permissions as $permission) {
                RolePermissions::create([
                    'role_id' => $role->id,
                    'permission_id' => $permission,
                ]);
            }

            DB::commit();
            return redirect()->route('roles.index')->with('success', 'Role created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Role creation failed: ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Failed to create role. Please try again.');
        }
    }

    public function edit(Request $request, $id)
    {
        if ($id == 1 || $id == '1') {
            return redirect()->route('roles.index')->with('error', 'You can not edit this role.');
        }
        $role = Role::with('permissions')->findOrFail($id);
        $permissions = Permission::all();
        return view('admin.roles.edit', compact('role', 'permissions'));
    }




    public function update(Request $request, $id)
    {
        if ($id == 1 || $id == '1') {
            return redirect()->route('roles.index')->with('error', 'You can not update this role.');
        }
        $request->validate([
            'name' => 'required|unique:roles,name, ' . $id,
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        DB::beginTransaction();

        try {
            $role = Role::findOrFail($id);
            $role->update(['name' => $request->name]);
            RolePermissions::where('role_id', $id)->delete();
            foreach ($request->permissions as $permission) {
                RolePermissions::create([
                    'role_id' => $role->id,
                    'permission_id' => $permission,
                ]);
            }

            DB::commit();
            return redirect()->route('roles.index')->with('success', 'Role updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Role creation failed: ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Failed to update role. Please try again.');
        }
    }

    public function destroy($id)
    {
        if ($id == 1 || $id == '1') {
            return redirect()->route('roles.index')->with('error', 'You can not delete this role.');
        }
        try {
            $role = Role::findOrFail($id);
            $role->delete();

            return response()->json([
                'status' => 200,
                'message' => 'Role deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Failed to delete role.'
            ], 500);
        }
    }
}
