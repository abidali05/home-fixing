<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Admin\ServiceCategoryModel;
use App\Models\BidModel;
use App\Models\JobRequestImages;
use App\Models\JobRequestModel;
use App\Models\Orders;
use App\Models\User;
use App\Notifications\BidAcceptedNotification;
use App\Notifications\BidRejectedNotification;
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
            'place_pictures.*' => 'image|max:8192',
            'video' => 'nullable|file|mimes:mp4,mov,ogg,qt,avi,webm|max:5120',
            'equipment_option' => 'nullable',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 'Validation failed.');
        }

        DB::beginTransaction();

        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return $this->error('Unauthorized.', 401);
            }

            $provider = User::findOrFail($request->provider_id);

            $equipmentOption = $request->equipment_option;

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
                'equipment_option' => $equipmentOption,
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

            // ✅ SAVE VIDEO
            if ($request->hasFile('video')) {
                $file = $request->file('video');
                $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/job_gallery/'), $filename);

                $job->video = $filename;
                $job->save();
            }

            // Create order with status 'open'
            $order = new Orders();
            $order->provider_id = $job->provider_id;
            $order->user_id = $user->id;
            $order->job_id = $job->id;
            $order->source = 'direct_hiring';
            $order->address = $job->address;
            $order->details = $job->description;
            $order->price = $job->price ?? 0;
            $order->status = 'open';
            $order->paid_to_system = 0;
            $order->save();

            DB::commit();

            // DirectHireNotification uses database channel, so it is saved in job_notifications.
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
            'address' => 'required|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'place_pictures' => 'required|array',
            'place_pictures.*' => 'image|max:8192',
            'video' => 'nullable|file|mimes:mp4,mov,ogg,qt,avi,webm|max:5120',
            'equipment_option' => 'nullable',
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

            $equipmentOption = $request->equipment_option;

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
                'equipment_option' => $equipmentOption,
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

            if ($request->hasFile('video')) {
                $file = $request->file('video');
                $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/job_gallery/'), $filename);

                $jobRequest->video = $filename;
                $jobRequest->save();
            }

            // Create order with status 'open'
            $order = new Orders();
            $order->provider_id = null;
            $order->user_id = $user->id;
            $order->job_id = $jobRequest->id;
            $order->source = 'bid';
            $order->address = $jobRequest->address;
            $order->details = $jobRequest->description;
            $order->price = $jobRequest->price ?? 0;
            $order->status = 'open';
            $order->paid_to_system = 0;
            $order->save();

            DB::commit();

            $providers = User::query()
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

            $order = Orders::with('provider')->where('job_id', $request->id)->latest()->first();
            if ($order) {
                $provider = $order->provider;
                if ($provider) {
                    $provider->profile_image = $provider->profile_image
                        ? asset('uploads/profile_images/' . $provider->profile_image)
                        : asset('assets/img/default.jpg');
                }
                $request->setAttribute('hired_provider', $provider);
                $request->setAttribute('order_status', $order->status);
            } else {
                $request->setAttribute('hired_provider', null);
                $request->setAttribute('order_status', null);
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
            $bids = BidModel::with('job', 'provider', 'order')->where('job_id', $id)->get();
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
            $customer = auth('sanctum')->user();
            if (!$customer) {
                return $this->error('Unauthorized.', 401);
            }
            if ((int) $customer->role !== 0) {
                return $this->error('Only customers can accept or reject bids.', 403);
            }

            $action = $request->input('status', 'accepted');

            $bid = BidModel::with('job')
                ->where('id', $id)
                ->where('status', 'pending')
                ->first();

            if (!$bid) {
                return $this->error('Bid not found.', 404);
            }

            $job = $bid->job;

            if (!in_array($job->status, ['pending', 'quoted'])) {
                return $this->error('This job request is not available.', 400);
            }

            if ((int) $job->user_id !== (int) $customer->id) {
                return $this->error('You are not allowed to update this bid.', 403);
            }

            if ($action === 'rejected') {
                $bid->status = 'rejected';
                $bid->save();

                DB::commit();

                $provider = User::find($bid->provider_id);
                if ($provider) {
                    try {
                        $provider->notify((new BidRejectedNotification($job, $customer))->afterCommit());
                    } catch (\Throwable $notificationException) {
                        Log::error('Failed to send bid rejected notification: ' . $notificationException->getMessage());
                    }
                }

                return $this->success(null, 'Bid rejected successfully.');
            }

            // $job->job_time = $bid->bid_time;
            $job->status = 'quoted';
            $job->save();

            // Reject other pending bids before accepting and send notifications
            $otherBids = BidModel::where('job_id', $job->id)
                ->where('id', '!=', $bid->id)
                ->where('status', 'pending')
                ->get();

            foreach ($otherBids as $otherBid) {
                $otherBid->status = 'rejected';
                $otherBid->save();

                $otherProvider = User::find($otherBid->provider_id);
                if ($otherProvider) {
                    try {
                        $otherProvider->notify(
                            (new BidRejectedNotification(
                                $job,
                                $customer,
                                'Better luck next time. Your offer was not accepted for this request.'
                            ))->afterCommit()
                        );
                    } catch (\Throwable $notificationException) {
                        Log::error('Failed to send auto-bid-rejected notification to provider ' . $otherBid->provider_id . ': ' . $notificationException->getMessage());
                    }
                }
            }

            $bid->status = 'accepted';
            $bid->save();

            // Create order
            // Update existing order for this job
            $order = Orders::where('job_id', $job->id)->first();

            if (!$order) {
                return $this->error('Order not found for this job.', 404);
            }

            $order->provider_id = $bid->provider_id;
            $order->price = $bid->price;
            $order->status = 'pending'; // or whatever status you want after bid acceptance
            $order->save();

            DB::commit();

            $provider = User::find($bid->provider_id);
            if ($provider) {
                try {
                    $provider->notify((new BidAcceptedNotification($job, $customer))->afterCommit());
                } catch (\Throwable $notificationException) {
                    Log::error('Failed to send bid accepted notification: ' . $notificationException->getMessage());
                }
            }

            return $this->success(null, 'Bid accepted successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error in accept_bid: ' . $e->getMessage());
            return $this->error('Failed to process bid.', 500);
        }
    }
}
