<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Jobs\SendJobPostedNotifications;
use App\Models\Admin\ServiceCategoryModel;
use App\Models\Admin\SystemSettingModel;
use App\Models\BidModel;
use App\Models\JobRequestImages;
use App\Models\JobRequestModel;
use App\Models\Orders;
use App\Models\OrderTracking;
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
    /**
     * Upload single media file or chunks for high-speed, reliable uploads.
     */
    public function upload_media(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:51200', // max 50MB per file or chunk
            'upload_id' => 'nullable|string|max:100',
            'chunk_index' => 'nullable|integer|min:0',
            'total_chunks' => 'nullable|integer|min:1',
            'file_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 'Validation failed.');
        }

        try {
            $file = $request->file('file');
            $uploadId = $request->input('upload_id');
            $chunkIndex = $request->input('chunk_index');
            $totalChunks = (int) $request->input('total_chunks', 1);

            $targetDir = public_path('uploads/job_gallery');
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            // Standard Single File Upload
            if (!$uploadId || $totalChunks <= 1 || $chunkIndex === null) {
                $ext = $file->getClientOriginalExtension() ?: 'bin';
                $filename = time() . '_' . Str::random(10) . '.' . $ext;
                $file->move($targetDir, $filename);

                return $this->success([
                    'file_name' => $filename,
                    'file_url' => asset('uploads/job_gallery/' . $filename),
                    'is_completed' => true,
                ], 'Media uploaded successfully.');
            }

            // Chunked Upload Handling
            $safeUploadId = preg_replace('/[^a-zA-Z0-9_-]/', '', $uploadId);
            $tempDir = storage_path('app/temp_chunks/' . $safeUploadId);
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0777, true);
            }

            $chunkFilename = 'chunk_' . $chunkIndex;
            $file->move($tempDir, $chunkFilename);

            // Check if all chunks have arrived
            $allUploaded = true;
            for ($i = 0; $i < $totalChunks; $i++) {
                if (!file_exists($tempDir . '/chunk_' . $i)) {
                    $allUploaded = false;
                    break;
                }
            }

            if (!$allUploaded) {
                return $this->success([
                    'upload_id' => $uploadId,
                    'chunk_index' => (int) $chunkIndex,
                    'total_chunks' => $totalChunks,
                    'is_completed' => false,
                ], 'Chunk ' . $chunkIndex . ' received successfully.');
            }

            // All chunks received -> Merge into final file
            $origName = $request->input('file_name', '');
            $ext = pathinfo($origName, PATHINFO_EXTENSION);
            if (!$ext) {
                $ext = 'bin';
            }

            $finalFilename = time() . '_' . Str::random(10) . '.' . $ext;
            $finalPath = $targetDir . '/' . $finalFilename;
            $out = fopen($finalPath, 'wb');

            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkPath = $tempDir . '/chunk_' . $i;
                $in = fopen($chunkPath, 'rb');
                stream_copy_to_stream($in, $out);
                fclose($in);
                @unlink($chunkPath);
            }
            fclose($out);
            @rmdir($tempDir);

            return $this->success([
                'upload_id' => $uploadId,
                'file_name' => $finalFilename,
                'file_url' => asset('uploads/job_gallery/' . $finalFilename),
                'is_completed' => true,
            ], 'All chunks merged and media uploaded successfully.');
        } catch (\Throwable $e) {
            Log::error('Error in upload_media: ' . $e->getMessage());
            return $this->error('Failed to upload media: ' . $e->getMessage(), 500);
        }
    }

    public function direct_hire(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'service_id' => 'required|exists:categories,id',
            'provider_id' => 'required|exists:users,id',
            'address' => 'required|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'description' => 'nullable|string',
            'job_date' => 'required|date',
            'job_time' => 'required|date_format:H:i',
            'place_pictures' => 'nullable|array',
            'place_pictures.*' => [
                function ($attribute, $value, $fail) {
                    if ($value instanceof \Illuminate\Http\UploadedFile) {
                        if (!$value->isValid()) {
                            $fail($attribute . ' must be a valid uploaded file.');
                        }
                    } elseif (is_string($value)) {
                        $clean = basename($value);
                        if (empty($clean) || !file_exists(public_path('uploads/job_gallery/' . $clean))) {
                            $fail($attribute . ' file not found on server: ' . $clean);
                        }
                    } else {
                        $fail($attribute . ' must be a valid image file or uploaded filename.');
                    }
                }
            ],
            'video' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if ($value instanceof \Illuminate\Http\UploadedFile) {
                        if (!$value->isValid()) {
                            $fail($attribute . ' must be a valid video file.');
                        }
                    } elseif (is_string($value)) {
                        $clean = basename($value);
                        if (empty($clean) || !file_exists(public_path('uploads/job_gallery/' . $clean))) {
                            $fail($attribute . ' video file not found on server: ' . $clean);
                        }
                    } else {
                        $fail($attribute . ' must be a valid video file or uploaded filename.');
                    }
                }
            ],
            'equipment_option' => 'nullable',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 'Validation failed.');
        }

        DB::beginTransaction();

        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                DB::rollBack();
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

            // ✅ SAVE IMAGES (Supports both uploaded files and pre-uploaded filenames)
            $placePictures = $request->input('place_pictures', []);
            if ($request->hasFile('place_pictures')) {
                foreach ($request->file('place_pictures') as $file) {
                    if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                        $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                        $file->move(public_path('uploads/job_gallery/'), $filename);

                        JobRequestImages::create([
                            'job_id' => $job->id,
                            'path' => $filename,
                        ]);
                    }
                }
            } elseif (is_array($placePictures)) {
                foreach ($placePictures as $item) {
                    if (is_string($item) && !empty($item)) {
                        $filename = basename($item);
                        JobRequestImages::create([
                            'job_id' => $job->id,
                            'path' => $filename,
                        ]);
                    }
                }
            }

            // ✅ SAVE VIDEO (Supports both uploaded file and pre-uploaded filename)
            if ($request->hasFile('video')) {
                $file = $request->file('video');
                if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                    $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                    $file->move(public_path('uploads/job_gallery/'), $filename);

                    $job->video = $filename;
                    $job->save();
                }
            } elseif (is_string($request->video) && !empty($request->video)) {
                $job->video = basename($request->video);
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
            'description' => 'nullable|string',
            'date' => 'required|date',
            'time' => 'required|date_format:H:i',
            'address' => 'required|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'place_pictures' => 'required|array',
            'place_pictures.*' => [
                function ($attribute, $value, $fail) {
                    if ($value instanceof \Illuminate\Http\UploadedFile) {
                        if (!$value->isValid()) {
                            $fail($attribute . ' must be a valid uploaded file.');
                        }
                    } elseif (is_string($value)) {
                        $clean = basename($value);
                        if (empty($clean) || !file_exists(public_path('uploads/job_gallery/' . $clean))) {
                            $fail($attribute . ' file not found on server: ' . $clean);
                        }
                    } else {
                        $fail($attribute . ' must be a valid image file or uploaded filename.');
                    }
                }
            ],
            'video' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if ($value instanceof \Illuminate\Http\UploadedFile) {
                        if (!$value->isValid()) {
                            $fail($attribute . ' must be a valid video file.');
                        }
                    } elseif (is_string($value)) {
                        $clean = basename($value);
                        if (empty($clean) || !file_exists(public_path('uploads/job_gallery/' . $clean))) {
                            $fail($attribute . ' video file not found on server: ' . $clean);
                        }
                    } else {
                        $fail($attribute . ' must be a valid video file or uploaded filename.');
                    }
                }
            ],
            'equipment_option' => 'nullable',
        ]);

        if ($validated->fails()) {
            return $this->validationError($validated->errors(), 'Validation failed.');
        }

        DB::beginTransaction();

        try {
            $user = auth('sanctum')->user();
            if ((int) $user->role !== 0) {
                DB::rollBack();
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

            // Save images (Supports both direct uploaded files and pre-uploaded filenames)
            $placePictures = $request->input('place_pictures', []);
            if ($request->hasFile('place_pictures')) {
                foreach ($request->file('place_pictures') as $file) {
                    if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                        $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                        $file->move(public_path('uploads/job_gallery/'), $filename);

                        JobRequestImages::create([
                            'job_id' => $jobRequest->id,
                            'path' => $filename,
                        ]);
                    }
                }
            } elseif (is_array($placePictures)) {
                foreach ($placePictures as $item) {
                    if (is_string($item) && !empty($item)) {
                        $filename = basename($item);
                        JobRequestImages::create([
                            'job_id' => $jobRequest->id,
                            'path' => $filename,
                        ]);
                    }
                }
            }

            // Save video (Supports both direct uploaded file and pre-uploaded filename)
            if ($request->hasFile('video')) {
                $file = $request->file('video');
                if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                    $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                    $file->move(public_path('uploads/job_gallery/'), $filename);

                    $jobRequest->video = $filename;
                    $jobRequest->save();
                }
            } elseif (is_string($request->video) && !empty($request->video)) {
                $jobRequest->video = basename($request->video);
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

            // Asynchronously dispatch notifications in Queue for instant response time
            SendJobPostedNotifications::dispatch($jobRequest->id)->afterCommit();

            return $this->success($jobRequest, 'Request Submitted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error submitting service request: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error('An error occurred while submitting the request.');
        }
    }

    public function my_service_requests()
    {
        try {
            $user = auth('sanctum')->user();

            $requests = JobRequestModel::with(['category', 'images', 'order'])
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->get();

            foreach ($requests as $request) {
                foreach ($request->images as $image) {
                    $image->path = $image->path != null
                        ? asset('uploads/job_gallery/' . $image->path)
                        : asset('assets/img/default.jpg');
                }

                $request->setAttribute('order_status', $request->order ? $request->order->status : null);
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
                $request->setAttribute('extra_amount', number_format((float) ($order->extra_amount ?? 0), 2, '.', ''));
                $request->setAttribute('extra_amount_reason', $order->extra_amount_reason);
                $request->setAttribute('extra_amount_status', (string) ($order->extra_amount_status ?: 'none'));
                $request->setAttribute('total_amount', number_format((float) ($order->total_amount ?: ($order->price + ($order->extra_amount_status !== 'rejected' ? $order->extra_amount : 0))), 2, '.', ''));
                $request->setAttribute('order', $order);
            } else {
                $request->setAttribute('hired_provider', null);
                $request->setAttribute('order_status', null);
                $request->setAttribute('extra_amount', '0.00');
                $request->setAttribute('extra_amount_reason', null);
                $request->setAttribute('extra_amount_status', 'none');
                $request->setAttribute('total_amount', '0.00');
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
            $settings = SystemSettingModel::first();
            $customerAppFee = ($settings && $settings->customer_app_fee !== null) ? (float) $settings->customer_app_fee : 0.00;

            $bids = BidModel::with('job', 'provider', 'order')->where('job_id', $id)->get();

            foreach ($bids as $bid) {
                $repairPrice = (float) ($bid->price ?? 0);
                $totalPayable = $repairPrice + $customerAppFee;

                $bid->customer_app_fee = number_format($customerAppFee, 2, '.', '');
                $bid->total_price = number_format($totalPayable, 2, '.', '');
                $bid->total_payable_by_customer = number_format($totalPayable, 2, '.', '');

                $bid->payment_breakdown = [
                    'bid_price' => number_format($repairPrice, 2, '.', ''),
                    'customer_app_fee' => number_format($customerAppFee, 2, '.', ''),
                    'subtotal' => number_format($totalPayable, 2, '.', ''),
                    'total_payable_by_customer' => number_format($totalPayable, 2, '.', ''),
                    'total_amount' => number_format($totalPayable, 2, '.', ''),
                    'total' => number_format($totalPayable, 2, '.', ''),
                ];
            }

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
                DB::rollBack();
                return $this->error('Unauthorized.', 401);
            }
            if ((int) $customer->role !== 0) {
                DB::rollBack();
                return $this->error('Only customers can accept or reject bids.', 403);
            }

            $action = $request->input('status', 'accepted');

            $bid = BidModel::with('job')
                ->where('id', $id)
                ->where('status', 'pending')
                ->first();

            if (!$bid) {
                DB::rollBack();
                return $this->error('Bid not found.', 404);
            }

            $job = $bid->job;

            if (!in_array($job->status, ['pending', 'quoted'])) {
                DB::rollBack();
                return $this->error('This job request is not available.', 400);
            }

            if ((int) $job->user_id !== (int) $customer->id) {
                DB::rollBack();
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
                $order = new Orders();
                $order->user_id = $job->user_id ?? $customer->id;
                $order->job_id = $job->id;
                $order->source = 'bid';
                $order->address = $job->address ?? '';
                $order->details = $job->description ?? '';
                $order->paid_to_system = 0;
            }

            $order->provider_id = $bid->provider_id;
            $order->price = $bid->price;
            $order->status = 'pending'; // or whatever status you want after bid acceptance
            $order->calculateAndSyncFinancials(false);
            $order->save();

            // Create or update initial tracking entity with 'pending' status
            $tracking = OrderTracking::where('order_id', $order->id)
                ->where('status', 'pending')
                ->first();

            if (!$tracking) {
                $tracking = new OrderTracking();
                $tracking->order_id = $order->id;
                $tracking->status = 'pending';
            }

            $tracking->latitude = $request->latitude ?? $order->latitude ?? $job->latitude ?? null;
            $tracking->longitude = $request->longitude ?? $order->longitude ?? $job->longitude ?? null;
            $tracking->save();

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
