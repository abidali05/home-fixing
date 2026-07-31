<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\ServiceCategoryModel;
use App\Models\CityModel;
use App\Models\ProviderGallery;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProviderController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = auth()->user() ?? auth('admin')->user();
        $isCompany = $currentUser && $currentUser->is_company;

        $query = User::query()
            ->with(['providerProfile'])
            ->where(function ($b) {
                $b->where('role', '1')
                    ->orWhere('role', 1)
                    ->orWhere('has_roles', '1')
                    ->orWhere('has_roles', 1)
                    ->orWhere('has_roles', 'like', '%1%')
                    ->orWhereRaw("FIND_IN_SET('1', has_roles)")
                    ->orWhereHas('providerProfile');
            });

        if ($isCompany) {
            $query->where('company_id', $currentUser->id);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhereHas('providerProfile', function ($profileQuery) use ($search) {
                        $profileQuery->where('company_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->filled('status')) {
            $query->where('provider_status', $request->status);
        }

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->city_id);
        }

        if ($request->filled('category_id')) {
            $categoryId = $request->category_id;

            $query->whereHas('providerProfile', function ($profileQuery) use ($categoryId) {
                $profileQuery->where(function ($builder) use ($categoryId) {
                    $builder->whereJsonContains('service_category', (string) $categoryId)
                        ->orWhereJsonContains('service_category', (int) $categoryId);
                });
            });
        }

        if ($request->filled('provider_type')) {
            $query->whereHas('providerProfile', function ($profileQuery) use ($request) {
                $profileQuery->where('provider_type', $request->provider_type);
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $providers = $query->latest()->paginate(15)->withQueryString();
        $companies = \App\Models\AdminUsers::where('is_company', true)->orderBy('name')->get();

        return view('admin.providers.index', [
            'providers' => $providers,
            'companies' => $companies,
            'cities' => CityModel::query()->orderBy('name')->get(),
            'services' => ServiceCategoryModel::query()->orderBy('name')->get(),
            'statuses' => ['active', 'inactive', 'suspended', 'banned'],
            'providerTypes' => ['individual', 'company'],
            'isCompany' => $isCompany
        ]);
    }

    public function assignCompany(Request $request, $id)
    {
        $provider = User::findOrFail($id);
        $provider->company_id = $request->input('company_id') ?: null;
        $provider->save();

        return back()->with('success', 'Provider company assignment updated successfully.');
    }

    public function create()
    {
        return view('admin.providers.create', [
            'cities' => CityModel::all(),
            'services' => ServiceCategoryModel::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email',
            'phone' => ['required', 'regex:/^\+9665[0-9]{8}$/', 'unique:users,phone'],
            'dob' => 'nullable|date|before:today',
            'city' => 'nullable|exists:cities,id',
            'address' => 'required|string',
            'provider_type' => 'nullable|in:individual,company',
            'company_name' => 'nullable|string|max:255',
            'service_category' => 'required|array',
            'service_category.*' => 'exists:categories,id',
            'experience' => 'nullable|string|max:255',
            'from_time' => 'nullable',
            'to_time' => 'nullable|after:from_time',
            'pricing_type' => 'nullable|in:hourly,fixed',
            'price_amount' => 'nullable|numeric|min:0',
            'bio' => 'nullable|string|max:2000',
            'profile_image' => 'nullable|image|max:8192',
            'document_type' => 'nullable|string|max:255',
            'document_number' => 'nullable|string|max:255',
            'license_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:8192',
            'certification_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:8192',
            'gallery' => 'nullable|array',
            'gallery.*' => 'image|max:8192',
            'status' => 'nullable|in:active,inactive,suspended,banned',
        ]);

        DB::beginTransaction();

        try {
            $profileImage = $this->movePublicFile($request, 'profile_image', 'uploads/profile_images');
            $serviceLicense = $this->movePublicFile($request, 'license_file', 'uploads/license_files');
            $certification = $this->movePublicFile($request, 'certification_file', 'uploads/certification_files');

            $provider = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'],
                'dob' => $validated['dob'] ?? null,
                'city_id' => $validated['city'] ?? null,
                'address' => $validated['address'],
                'profile_image' => $profileImage,
                'role' => '1',
                'has_roles' => '0,1',
                'status' => $validated['status'] ?? 'inactive',
                'provider_status' => $validated['status'] ?? 'inactive',
            ]);

            ProviderProfile::create([
                'user_id' => $provider->id,
                'provider_type' => $validated['provider_type'] ?? 'individual',
                'company_name' => $validated['company_name'] ?? null,
                'address' => $validated['address'],
                'service_category' => $validated['service_category'],
                'experience' => $validated['experience'] ?? null,
                'work_hour_start' => $validated['from_time'] ?? null,
                'work_hour_end' => $validated['to_time'] ?? null,
                'bio' => $validated['bio'] ?? null,
                'charge_type' => $validated['pricing_type'] ?? null,
                'charge_amount' => $validated['price_amount'] ?? null,
                'document_type' => $validated['document_type'] ?? null,
                'document_number' => $validated['document_number'] ?? null,
                'service_license' => $serviceLicense,
                'certification' => $certification,
            ]);

            if ($request->hasFile('gallery')) {
                foreach ($request->file('gallery') as $image) {
                    $filename = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
                    $image->move(public_path('uploads/provider_gallery/'), $filename);

                    ProviderGallery::create([
                        'user_id' => $provider->id,
                        'path' => $filename,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('providers.index')->with('success', 'Provider created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Provider creation failed: ' . $e->getMessage());

            return back()->with('error', 'An error occurred while creating the provider.')->withInput();
        }
    }

    public function show($id)
    {
        $provider = User::query()
            ->with('providerProfile')
            ->whereHas('providerProfile')
            ->findOrFail($id);

        $gallery = ProviderGallery::query()->where('user_id', $provider->id)->get();

        return view('admin.providers.show', compact('provider', 'gallery'));
    }

    public function edit($id)
    {
        $provider = User::query()
            ->with('providerProfile')
            ->whereHas('providerProfile')
            ->findOrFail($id);

        $this->mergeProviderProfileIntoUser($provider);

        return view('admin.providers.edit', [
            'provider' => $provider,
            'cities' => CityModel::all(),
            'services' => ServiceCategoryModel::all(),
            'gallery' => ProviderGallery::where('user_id', $id)->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $provider = User::query()
            ->with('providerProfile')
            ->whereHas('providerProfile')
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email,' . $provider->id,
            'phone' => ['required', 'regex:/^\+9665[0-9]{8}$/', 'unique:users,phone,' . $provider->id],
            'dob' => 'nullable|date|before:today',
            'city' => 'nullable|exists:cities,id',
            'address' => 'required|string',
            'provider_type' => 'nullable|in:individual,company',
            'company_name' => 'nullable|string|max:255',
            'service_category' => 'required|array',
            'service_category.*' => 'exists:categories,id',
            'experience' => 'nullable|string|max:255',
            'from_time' => 'nullable',
            'to_time' => 'nullable|after:from_time',
            'pricing_type' => 'nullable|in:hourly,fixed',
            'price_amount' => 'nullable|numeric|min:0',
            'bio' => 'nullable|string|max:2000',
            'profile_image' => 'nullable|image|max:8192',
            'document_type' => 'nullable|string|max:255',
            'document_number' => 'nullable|string|max:255',
            'license_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:8192',
            'certification_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:8192',
            'gallery' => 'nullable|array',
            'gallery.*' => 'image|max:8192',
            'status' => 'required|in:active,inactive,suspended,banned',
        ]);

        DB::beginTransaction();

        try {
            if ($request->hasFile('profile_image')) {
                if (!empty($provider->profile_image) && file_exists(public_path('uploads/profile_images/' . $provider->profile_image))) {
                    @unlink(public_path('uploads/profile_images/' . $provider->profile_image));
                }

                $provider->profile_image = $this->movePublicFile($request, 'profile_image', 'uploads/profile_images');
            }

            $provider->name = $validated['name'];
            $provider->email = $validated['email'] ?? null;
            $provider->phone = $validated['phone'];
            $provider->dob = $validated['dob'] ?? null;
            $provider->city_id = $validated['city'] ?? null;
            $provider->provider_status = $validated['status'];

            if ((string) $provider->role === '1') {
                $provider->status = $validated['status'];
            }

            $provider->save();

            $profile = $provider->providerProfile;

            if ($request->hasFile('license_file')) {
                if (!empty($profile->service_license) && file_exists(public_path('uploads/license_files/' . $profile->service_license))) {
                    @unlink(public_path('uploads/license_files/' . $profile->service_license));
                }

                $profile->service_license = $this->movePublicFile($request, 'license_file', 'uploads/license_files');
            }

            if ($request->hasFile('certification_file')) {
                if (!empty($profile->certification) && file_exists(public_path('uploads/certification_files/' . $profile->certification))) {
                    @unlink(public_path('uploads/certification_files/' . $profile->certification));
                }

                $profile->certification = $this->movePublicFile($request, 'certification_file', 'uploads/certification_files');
            }

            $profile->fill([
                'provider_type' => $validated['provider_type'] ?? $profile->provider_type,
                'company_name' => $validated['company_name'] ?? null,
                'address' => $validated['address'],
                'service_category' => $validated['service_category'],
                'experience' => $validated['experience'] ?? null,
                'work_hour_start' => $validated['from_time'] ?? null,
                'work_hour_end' => $validated['to_time'] ?? null,
                'bio' => $validated['bio'] ?? null,
                'charge_type' => $validated['pricing_type'] ?? null,
                'charge_amount' => $validated['price_amount'] ?? null,
                'document_type' => $validated['document_type'] ?? null,
                'document_number' => $validated['document_number'] ?? null,
            ]);
            $profile->save();

            if ($request->hasFile('gallery')) {
                foreach ($request->file('gallery') as $image) {
                    $filename = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
                    $image->move(public_path('uploads/provider_gallery/'), $filename);

                    ProviderGallery::create([
                        'user_id' => $provider->id,
                        'path' => $filename,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('providers.index')->with('success', 'Provider updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Provider update failed: ' . $e->getMessage());

            return back()->with('error', 'An error occurred while updating the provider.')->withInput();
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $provider = User::query()->whereHas('providerProfile')->findOrFail($id);

        $validated = $request->validate([
            'provider_status' => 'required|in:active,inactive,suspended,banned',
        ]);

        $provider->provider_status = $validated['provider_status'];

        if ((string) $provider->role === '1') {
            $provider->status = $validated['provider_status'];
        }

        $provider->save();

        return back()->with('success', 'Provider status updated successfully.');
    }

    public function deleteProviderImage($id)
    {
        $image = ProviderGallery::find($id);

        if ($image) {
            $total = ProviderGallery::where('user_id', $image->user_id)->count();

            if ($total === 1) {
                return back()->with('error', 'You can not delete last image.');
            }

            if (file_exists(public_path('uploads/provider_gallery/' . $image->path))) {
                @unlink(public_path('uploads/provider_gallery/' . $image->path));
            }

            $image->delete();
        }

        return back()->with('success', 'Image deleted successfully.');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json([
            'status' => 200,
            'message' => 'User deleted successfully.',
        ]);
    }

    private function mergeProviderProfileIntoUser(User $provider): void
    {
        $profile = $provider->providerProfile;

        if (!$profile) {
            return;
        }

        $provider->address = $profile->address;
        $provider->service_category = $profile->service_category ?? [];
        $provider->experience = $profile->experience;
        $provider->work_hour_start = $profile->work_hour_start;
        $provider->work_hour_end = $profile->work_hour_end;
        $provider->bio = $profile->bio;
        $provider->charge_type = $profile->charge_type;
        $provider->charge_amount = $profile->charge_amount;
        $provider->document_type = $profile->document_type;
        $provider->document_number = $profile->document_number;
        $provider->provider_type = $profile->provider_type;
        $provider->company_name = $profile->company_name;
        $provider->service_license = $profile->service_license;
        $provider->certification = $profile->certification;
        $provider->status = $provider->provider_status ?: $provider->status;
    }

    private function movePublicFile(Request $request, string $field, string $directory): ?string
    {
        if (!$request->hasFile($field)) {
            return null;
        }

        $file = $request->file($field);
        $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
        $file->move(public_path(trim($directory, '/')), $filename);

        return $filename;
    }
}
