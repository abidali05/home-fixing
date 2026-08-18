<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\Orders;
use App\Models\BidModel;
use App\Models\Admin\Role;
use App\Models\AdminUsers;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = AdminUsers::where('is_company', true)->orderBy('id', 'desc')->get();

            return DataTables::of($data)
                ->addIndexColumn()
                ->editColumn('status', function ($row) {
                    return $row->status == 'active' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';
                })
                ->addColumn('providers_count', function ($row) {
                    return User::where('company_id', $row->id)->count();
                })
                ->addColumn('action', function ($row) {
                    $editUrl = route('companies.edit', $row->id);
                    $assignUrl = route('companies.assign.form', $row->id);
                    $showUrl = route('companies.show', $row->id);

                    return '
                        <a href="' . $showUrl . '" class="text-info me-2" title="View Stats"><i class="bi bi-eye-fill fs-5"></i></a>
                        <a href="' . $editUrl . '" class="text-primary me-2" title="Edit"><i class="bi bi-pencil-square fs-5"></i></a>
                        <a href="' . $assignUrl . '" class="text-success me-2" title="Assign Providers"><i class="bi bi-person-plus-fill fs-5"></i></a>
                        <a href="javascript:void(0);" class="text-danger deleteCompanyBtn" data-id="' . $row->id . '" title="Delete"><i class="bi bi-trash-fill fs-5"></i></a>
                    ';
                })
                ->rawColumns(['status', 'action'])
                ->make(true);
        }

        return view('admin.companies.index');
    }

    public function create()
    {
        return view('admin.companies.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admin_users,email',
            'phone' => ['required', 'regex:/^\+9665[0-9]{8}$/', 'unique:admin_users,phone'],
            'password' => 'required|string|min:6',
            'address' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        $role = Role::firstOrCreate(['name' => 'Company']);

        AdminUsers::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => $role->id,
            'address' => $request->address,
            'status' => $request->status,
            'is_company' => true,
            'company_name' => $request->company_name,
        ]);

        return redirect()->route('companies.index')->with('success', 'Company user created successfully.');
    }

    public function edit($id)
    {
        $company = AdminUsers::where('is_company', true)->findOrFail($id);
        return view('admin.companies.edit', compact('company'));
    }

    public function update(Request $request, $id)
    {
        $company = AdminUsers::where('is_company', true)->findOrFail($id);

        $request->validate([
            'company_name' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admin_users,email,' . $id,
            'phone' => ['required', 'regex:/^\+9665[0-9]{8}$/', 'unique:admin_users,phone,' . $id],
            'password' => 'nullable|string|min:6',
            'address' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'status' => $request->status,
            'company_name' => $request->company_name,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $company->update($data);

        return redirect()->route('companies.index')->with('success', 'Company user updated successfully.');
    }

    public function destroy($id)
    {
        $company = AdminUsers::where('is_company', true)->findOrFail($id);
        
        // Dissociate any assigned providers
        User::where('company_id', $company->id)->update(['company_id' => null]);

        $company->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Company user deleted successfully.'
        ]);
    }

    public function assignProvidersForm($id)
    {
        $currentUser = auth()->user() ?? auth('admin')->user();
        if ($currentUser && $currentUser->is_company) {
            return redirect()->route('dashboard')->with('error', 'Company accounts cannot assign providers manually.');
        }

        $company = AdminUsers::where('is_company', true)->findOrFail($id);
        
        $providers = User::where(function ($q) {
            $q->where('role', '1')->orWhere('has_roles', '1')->orWhere('has_roles', 'like', '%1%');
        })
        ->orderBy('name')
        ->get();

        $assignedProviderIds = User::where('company_id', $id)->pluck('id')->toArray();

        return view('admin.companies.assign', compact('company', 'providers', 'assignedProviderIds'));
    }

    public function assignProviders(Request $request, $id)
    {
        $currentUser = auth()->user() ?? auth('admin')->user();
        if ($currentUser && $currentUser->is_company) {
            return redirect()->route('dashboard')->with('error', 'Company accounts cannot assign providers manually.');
        }

        $company = AdminUsers::where('is_company', true)->findOrFail($id);

        DB::beginTransaction();
        try {
            // First, clear previous assignments
            User::where('company_id', $id)->update(['company_id' => null]);

            // Set new assignments
            if ($request->filled('providers')) {
                User::whereIn('id', $request->providers)->update(['company_id' => $id]);
            }

            DB::commit();
            return redirect()->route('companies.index')->with('success', 'Providers assigned to company successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to assign providers.');
        }
    }

    public function show($id)
    {
        $currentUser = auth()->user() ?? auth('admin')->user();
        if ($currentUser && $currentUser->is_company) {
            $id = $currentUser->id;
        }

        $company = AdminUsers::where('is_company', true)->findOrFail($id);
        
        $providerIds = User::where('company_id', $id)
            ->where(function ($b) {
                $b->where('role', '1')
                    ->orWhere('role', 1)
                    ->orWhere('has_roles', '1')
                    ->orWhere('has_roles', 1)
                    ->orWhere('has_roles', 'like', '%1%')
                    ->orWhereRaw("FIND_IN_SET('1', has_roles)")
                    ->orWhereHas('providerProfile');
            })
            ->pluck('id');
        
        $providersCount = $providerIds->count();
        $serviceRequestsCount = Orders::whereIn('provider_id', $providerIds)->count();
        $bidsCount = BidModel::whereIn('provider_id', $providerIds)->count();

        $assignedProviders = User::whereIn('id', $providerIds)->orderBy('name')->get();

        return view('admin.companies.show', compact('company', 'providersCount', 'serviceRequestsCount', 'bidsCount', 'assignedProviders'));
    }
}
