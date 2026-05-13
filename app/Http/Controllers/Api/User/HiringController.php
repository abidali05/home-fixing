<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Admin\ServiceCategoryModel;
use App\Models\BidModel;
use App\Models\JobRequestImages;
use App\Models\JobRequestModel;
use App\Models\Orders;
use App\Models\User;
use App\Notifications\DirectHireNotification;
use App\Notifications\JobPostedNotification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class HiringController extends Controller
{
    public function direct_hire(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'service_id' => 'required|exists:categories,id',
            'provider_id' => 'required|exists:users,id',
            'address' => 'required|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'description' => 'required|string',
            'job_date' => 'required|date',
            'job_time' => 'required|date_format:H:i',
            'place_pictures' => 'nullable|array',
            'place_pictures.*' => 'image|max:8192'
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 'Validation failed.');
        }

        DB::beginTransaction();

        try {
            $user = auth('sanctum')->user();
            $provider = User::findOrFail($request->provider_id);

            $job = JobRequestModel::create([
                'category_id' => $request->service_id,
                'provider_id' => $request->provider_id,
                'user_id' => $user->id,
                'address' => $request->address,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'description' => $request->description,
                'job_date' => date('Y-m-d', strtotime($request->job_date)),
                'job_time' => $request->job_time ?? date('H:i'),
                'status' => 'pending',
                'price_type' => $provider->charge_type,
                'price' => $provider->charge_amount,
            ]);

            // ✅ SAVE IMAGES
            if ($request->hasFile('place_pictures')) {
                foreach ($request->file('place_pictures') as $file) {
                    $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                    $file->move(public_path('uploads/job_gallery/'), $filename);

                    JobRequestImages::create([
                        'job_id' => $job->id,
                        'path' => $filename,
                    ]);
                }
            }

            DB::commit();

            $provider->notify((new DirectHireNotification($job, $user))->afterCommit());

            return $this->success($job, 'Request Submitted successfully.');
        } catch (\Exception $e) {

            DB::rollBack();

            return $this->error('Failed to create job request. Please try again later.', 500, [
                'exception' => $e->getMessage()
            ]);
        }
    }

    public function post_service_request(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'service_id' => 'required|exists:categories,id',
            'description' => 'required|string',
            'date' => 'required|date',
            'time' => 'required|date_format:H:i',
            // 'price' => 'required|numeric|min:0',
            'address' => 'required|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'place_pictures' => 'required|array',
            'place_pictures.*' => 'image|max:8192'
        ]);

        if ($validated->fails()) {
            return $this->validationError($validated->errors(), 'Validation failed.');
        }

        DB::beginTransaction();

        try {
            $user = auth('sanctum')->user();
            if ((int) $user->role !== 0) {
                return $this->error('Only normal users can create service requests.', 403);
            }

            $jobRequest = JobRequestModel::create([
                'user_id' => $user->id,
                'category_id' => $request->service_id,
                'description' => $request->description,
                'job_date' => $request->date,
                'job_time' => $request->time,
                'price' => $request->price ?? 0,
                'price_type' => 'fixed',
                'address' => $request->address,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
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

            $providers = User::query()
                ->where('role', 1)
                ->whereNotNull('fcm_token')
                ->where('fcm_token', '!=', '')
                ->whereHas('providerProfile', function ($q) use ($jobRequest) {
                    $categoryId = (int) $jobRequest->category_id;

                    $q->where(function ($sub) use ($categoryId) {
                        $sub->whereJsonContains('service_category', $categoryId)
                            ->orWhereJsonContains('service_category', (string) $categoryId);
                    });
                })
                ->get();

            Notification::send($providers, (new JobPostedNotification($jobRequest))->afterCommit());
            return $this->success($jobRequest, 'Request Submitted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::info($e->getMessage());
            return $this->error('An error occurred while submitting the request.');
        }
    }

    public function my_service_requests()
    {
        try {
            $user = auth('sanctum')->user();

            $requests = JobRequestModel::with(['category', 'images'])
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->get();

            foreach ($requests as $request) {
                foreach ($request->images as $image) {
                    $image->path = $image->path != null
                        ? asset('uploads/job_gallery/' . $image->path)
                        : asset('assets/img/default.jpg');
                }
            }

            return $this->success($requests);
        } catch (\Throwable $e) {
            Log::error('Error in my_service_requests: ' . $e->getMessage());
            return $this->error('Failed to load service requests.', 500);
        }
    }


    public function service_request_details($id)
    {
        try {
            $request = JobRequestModel::with(['user', 'images'])->where('id', $id)->where('status', '!=', ['completed', 'cancelled'])->firstOrFail();
            $category = ServiceCategoryModel::where('id', $request->category_id)->first();
            $category->path = $category->path != null ? asset('uploads/service_category/' . $category->path) : asset('assets/img/default.jpg');
            $request->category = $category;

            foreach ($request->images as $image) {
                $image->path = $image->path != null ? asset('uploads/job_gallery/' . $image->path) : asset('assets/img/default.jpg');
            }

            return $this->success($request);
        } catch (ModelNotFoundException $e) {
            return $this->error('Service request not found.', 404);
        } catch (\Throwable $e) {
            Log::error('Error in service_request_details: ' . $e->getMessage());
            return $this->error('Failed to load service request details.', 500);
        }
    }

    public function view_bids_by_request($id)
    {
        try {
            $bids = BidModel::with('job', 'provider','order')->where('job_id', $id)->get();
            return $this->success($bids);
        } catch (\Throwable $e) {
            Log::error('Error in view_bids_by_request: ' . $e->getMessage());
            return $this->error('Failed to load bids.', 500);
        }
    }

    public function accept_bid(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $action = $request->input('status', 'accepted');

            $bid = BidModel::with('job')
                ->where('id', $id)
                ->where('status', 'pending')
                ->first();

            if (!$bid) {
                return $this->error('Bid not found.', 404);
            }

            $job = $bid->job;

            if ($job->status != 'pending') {
                return $this->error('This job request is not available.', 400);
            }

            if ($action === 'rejected') {
                $bid->status = 'rejected';
                $bid->save();

                DB::commit();
                return $this->success(null, 'Bid rejected successfully.');
            }

            $job->job_time = $bid->bid_time;
            $job->save();

            $bid->status = 'accepted';
            $bid->save();

            $job->status = 'quoted';
            $job->save();

            // Reject other pending bids
            BidModel::where('job_id', $job->id)
                ->where('status', 'pending')
                ->update(['status' => 'rejected']);

            // Create order
            $order = new Orders();
            $order->provider_id = $bid->provider_id;
            $order->user_id = $job->user_id;
            $order->job_id = $job->id;
            $order->source = 'bid';
            $order->address = $job->address;
            $order->details = $job->description;
            $order->price = $bid->price;
            $order->status = 'pending';
            $order->paid_to_system = 0;
            $order->save();

            DB::commit();

            return $this->success(null, 'Bid accepted successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error in accept_bid: ' . $e->getMessage());
            return $this->error('Failed to process bid.', 500);
        }
    }
}



