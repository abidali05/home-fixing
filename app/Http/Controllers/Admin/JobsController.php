<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use App\Models\User;
use App\Models\BidModel;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\JobRequestModel;
use App\Models\JobRequestImages;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\Admin\RolePermissions;
use Yajra\DataTables\Facades\DataTables;
use App\Models\Admin\ServiceCategoryModel;

class JobsController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = JobRequestModel::with('user', 'category')->orderBy('id', 'desc')->get();
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

                ->addColumn('user_name', function ($row) {
                    return $row->user->name;
                })

                ->addColumn('service_name', function ($row) {
                    return $row->category->name;
                })


                ->editColumn('status', function ($row) {
                    return match ($row->status) {
                        'pending' => '<span class="badge bg-warning">Pending</span>',
                        'quoted' => '<span class="badge bg-success">Quoted</span>',
                        'in_progress' => '<span class="badge bg-danger">In Progress</span>',
                        default => '<span class="badge bg-secondary">Complete</span>',
                    };
                })

                ->editColumn('created_at', function ($row) {
                    return Carbon::parse($row->created_at)->format('d F Y');
                })

                ->editColumn('job_date', function ($row) {
                    return Carbon::parse($row->job_date)->format('d F Y');
                })


                ->addColumn('action', function ($row) use ($rolePermissions) {
                    $editUrl = route('job_requests.edit', $row->id);
                    $viewUrl = route('job_requests.details', $row->id);

                    $actionBtns = '<a href="' . $viewUrl . '" class="btn btn-sm btn-link text-primary me-2">
                        <i class="bi bi-eye"></i>
                    </a>';
               
                    if ( in_array(24, $rolePermissions)) {
                        $actionBtns .= '<a href="' . $editUrl . '" class="btn btn-sm btn-link text-primary me-3"><i class="bi bi-pencil-square"></i></a>';
                    }
                    if (in_array(25, $rolePermissions)) {
                        $actionBtns .= '<a href="javascript:void(0);" class="btn btn-sm btn-link text-danger deleteServiceRequestBtn" data-id="' . $row->id . '"><i class="bi bi-trash-fill"></i></a>';
                    }
                    return $actionBtns;
                })

                ->rawColumns(['profile_image', 'status', 'action'])
                ->make(true);
        }

        return view('admin.job_requests.index');
    }

    public function create()
    {
        $services = ServiceCategoryModel::all();
        $users = User::where('role', '0')->where('status', 'active')->get();
        return view('admin.job_requests.create', compact('services', 'users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'service_id' => 'required|exists:categories,id',
            'instructions' => 'required|string|max:2000',
            'date' => 'required|date',
            'time' => 'required|date_format:H:i',
            'price' => 'required|numeric|min:0',
            'address' => 'required|string|max:255',
            'place_pictures' => 'nullable|array',
            'place_pictures.*' => 'image|max:8192',
        ]);

        DB::beginTransaction();

        try {

            $jobRequest = JobRequestModel::create([
                'user_id' => $validated['user_id'],
                'category_id' => $validated['service_id'],
                'description' => $validated['instructions'],
                'job_date' => $validated['date'],
                'job_time' => $validated['time'],
                'price' => $validated['price'],
                'price_type' => 'fixed',
                'address' => $validated['address'],
                'status' => 'pending',
            ]);

            if ($request->hasFile('place_pictures')) {
                foreach ($request->file('place_pictures') as $file) {
                    $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                    $file->move(public_path('uploads/job_gallery/'), $filename);

                    JobRequestImages::create([
                        'job_id' => $jobRequest->id,
                        'path' => $filename,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('job_requests.index')->with('success', 'Service request created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Service request creation failed: ' . $e->getMessage());

            return back()->with('error', 'Something went wrong. Please try again.')->withInput();
        }
    }

    public function details($id)
    {
        $jobRequest = JobRequestModel::with('user', 'category')->where('id', $id)->firstOrFail();
        $bid = BidModel::with('provider', 'job')->where('job_id', $id)->where('status', 'accepted')->first();
        $images = JobRequestImages::where('job_id', $id)->get();

        return view('admin.job_requests.details', compact('jobRequest', 'images', 'bid'));
    }

    public function edit($id)
    {
        $jobRequest = JobRequestModel::with('user', 'category')->where('id', $id)->firstOrFail();
        $services = ServiceCategoryModel::all();
        $users = User::where('role', '0')->where('status', 'active')->get();
        $images = JobRequestImages::where('job_id', $id)->get();

        return view('admin.job_requests.edit', compact('jobRequest', 'services', 'users', 'images'));
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'service_id' => 'required|exists:categories,id',
            'description' => 'required|string|max:2000',
            'date' => 'required|date',
            'time' => 'required',
            'price' => 'required|numeric|min:0',
            'address' => 'required|string|max:255',
            'place_pictures' => 'nullable|array',
            'place_pictures.*' => 'image|max:8192',
        ]);

        DB::beginTransaction();

        try {
            $jobRequest = JobRequestModel::findOrFail($id);

            $jobRequest->update([
                'user_id' => $validated['user_id'],
                'category_id' => $validated['service_id'],
                'description' => $validated['description'],
                'job_date' => $validated['date'],
                'job_time' => $validated['time'],
                'price' => $validated['price'],
                'address' => $validated['address'],
            ]);

            if ($request->hasFile('place_pictures')) {
                foreach ($request->file('place_pictures') as $file) {
                    $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                    $file->move(public_path('uploads/job_gallery/'), $filename);

                    JobRequestImages::create([
                        'job_id' => $jobRequest->id,
                        'path' => $filename,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('job_requests.index')->with('success', 'Service request updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Service request update failed: ' . $e->getMessage());

            return back()->with('error', 'Something went wrong. Please try again.')->withInput();
        }
    }

    public function deleteJobImage($id)
    {
        $image = JobRequestImages::findOrFail($id);

        $imagePath = public_path('uploads/job_gallery/' . $image->path);
        $image->delete();
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
        return redirect()->back()->with('success', 'Image deleted successfully.');
    }

    public function destroy($id)
    {
        $jobRequest = JobRequestModel::findOrFail($id);
        $images = JobRequestImages::where('job_id', $id)->get();
        foreach ($images as $image) {
            $imagePath = public_path('uploads/job_gallery/' . $image->path);
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
            $image->delete();
        }
        $jobRequest->delete();
        return response()->json([
            'status' => 200,
            'message' => 'Service request deleted successfully.'
        ]);
    }
}
