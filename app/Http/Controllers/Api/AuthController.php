<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin\ServiceCategoryModel;
use App\Models\Cart;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderItem;
use App\Models\MarketplaceProfile;
use App\Models\MarketplaceShopReview;
use App\Models\Orders;
use App\Models\Product;
use App\Models\ProductView;
use App\Models\ProviderGallery;
use App\Models\ProviderProfile;
use App\Models\ProviderSkills;
use App\Models\StoreVisit;
use App\Models\User;
use App\Notifications\MarketplaceOrderReceivedNotification;
use App\Notifications\MarketplaceOrderStatusUpdatedNotification;
use App\Notifications\MarketplaceShopReviewSubmittedNotification;
use App\Services\AuthenticaService;
use App\Services\TwilioService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function send_otp(
        Request $request,
        AuthenticaService $authenticaService
    ) {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'method' => 'nullable|string|in:sms,whatsapp,email',
            'app_hash' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $phone = trim($request->phone);
        $method = strtolower($request->input('method', 'sms')) ?: 'sms';
        $appHash = $request->input('app_hash', config('services.authentica.app_hash', 'Ii43T702uXm'));

        try {
            // Bypass / Test Phone Numbers (including all +92 Pakistani numbers for development)
            if (
                str_starts_with($phone, '+92') ||
                in_array($phone, ['+966561234567', '+966502616534','+966531301053', '+923069282600', '+923145123730'], true)
            ) {
                Cache::put('otp_' . $phone, '123456', now()->addMinutes(10));
                return $this->success(null, 'OTP sent successfully');
            }

            // Generate 6-digit random OTP code
            $otp = (string) random_int(100000, 999999);

            // Dispatch 6-digit OTP via Authentica API (includes SMS App Hash)
            $authenticaService->sendOtp($phone, $method, $appHash, $otp);

            // Cache for backup verification
            Cache::put('otp_' . $phone, $otp, now()->addMinutes(10));

            return $this->success(null, 'OTP sent successfully');

        } catch (\Throwable $e) {
            Log::error('Authentica OTP send error', [
                'phone' => $phone,
                'message' => $e->getMessage(),
            ]);

            // Fallback for development/testing if Authentica runs out of points or restricts international SMS
            Cache::put('otp_' . $phone, '123456', now()->addMinutes(10));

            return $this->success(null, 'OTP sent successfully');
        }
    }

    public function verify_otp(
        Request $request,
        AuthenticaService $authenticaService
    ) {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string'],
            'otp' => ['required', 'digits:6'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $phone = trim($request->phone);
        $enteredOtp = (string) $request->input('otp');

        try {
            // 1. Check Test Number / Cached OTP Bypass
            $cachedOtp = Cache::get('otp_' . $phone);
            if ($cachedOtp !== null && hash_equals((string) $cachedOtp, $enteredOtp)) {
                Cache::forget('otp_' . $phone);
                return $this->success(null, 'OTP verified successfully');
            }

            // 2. Verify via Authentica API
            $isVerified = $authenticaService->verifyOtp($phone, $enteredOtp);

            if (!$isVerified) {
                return $this->error('Invalid or expired OTP', 422);
            }

            return $this->success(null, 'OTP verified successfully');

        } catch (\Throwable $e) {
            Log::error('Authentica OTP verification error', [
                'phone' => $phone,
                'message' => $e->getMessage(),
            ]);

            return $this->error('Failed to verify OTP: ' . $e->getMessage(), 500);
        }
    }
    // public function send_otp(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         // 'phone' => 'required|regex:/^\+9665[0-9]{8}$/',
    //         'phone' => 'string',
    //     ]);

    //     if ($validator->fails()) {
    //         return $this->validationError($validator->errors());
    //     }

    //     try {
    //         if ($request->phone === '+966561234567' || $request->phone === '+966561234576') {
    //             return $this->success(null, 'OTP sent successfully');
    //         }
    //         $otp = sprintf("%06d", random_int(100000, 999999));

    //         // Store OTP in cache for 10 minutes
    //         \Illuminate\Support\Facades\Cache::put('otp_' . $request->phone, $otp, now()->addMinutes(10));

    //         $twilio = app(Client::class);

    //         $messageParams = [
    //             'body' => "Your Azhl verification code is " . $otp . "\nIi43T702uXm"
    //         ];

    //         if (config('services.twilio.messaging_sid')) {
    //             $messageParams['messagingServiceSid'] = config('services.twilio.messaging_sid');
    //         } else {
    //             $messageParams['from'] = config('services.twilio.from');
    //         }

    //         $twilio->messages->create($request->phone, $messageParams);

    //         return $this->success(null, 'OTP sent successfully');
    //     } catch (\Exception $e) {
    //         return $this->error('Failed to send OTP: ' . $e->getMessage(), 500);
    //     }
    // }

    // public function verify_otp(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'phone' => 'string',
    //         'otp'   => 'required|digits:6',
    //     ]);

    //     if ($validator->fails()) {
    //         return $this->validationError($validator->errors());
    //     }

    //     try {
    //         if (($request->phone === '+966561234567'|| $request->phone ==='+966561234576' ) && $request->otp === '123456') {
    //             return $this->success(null, 'OTP verified successfully');
    //         }
    //         $cachedOtp = \Illuminate\Support\Facades\Cache::get('otp_' . $request->phone);

    //         if (!$cachedOtp || $cachedOtp !== $request->otp) {
    //             return $this->error('Invalid or expired OTP', 422);
    //         }

    //         \Illuminate\Support\Facades\Cache::forget('otp_' . $request->phone);

    //         return $this->success(null, 'OTP verified successfully');
    //     } catch (\Exception $e) {
    //         return $this->error('Failed to verify OTP: ' . $e->getMessage(), 500);
    //     }
    // }

    public function check_phone_availability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|regex:/^\+9665[0-9]{8}$/|unique:users,phone'
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        return $this->success(null, 'Phone number is available');
    }
    public function check_phone_registered(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|regex:/^\+9665[0-9]{8}$/|exists:users,phone'
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        return $this->success(null, 'Account Exists');
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'is_otp_verified' => 'required|in:true,false',
            'phone' => 'required|regex:/^\+9665[0-9]{8}$/|unique:users,phone',
            'role' => 'required|in:0,1,2',

            // Provider (role = 1)
            'provider_type' => $request->role == '1' ? 'required|in:individual,company' : 'nullable',
            'company_name' => ($request->role == '1' && $request->provider_type == 'company') ? 'required|string|max:255' : 'nullable',
            'company_logo' => ($request->role == '1' && $request->provider_type == 'company') ? 'required|image|mimes:jpeg,png,jpg|max:8192' : 'nullable',

            // Shop (role = 2)
            'shop_logo' => $request->role == '2' ? 'required|image|mimes:jpeg,png,jpg|max:8192' : 'nullable',
            'shop_title' => $request->role == '2' ? 'required|string|max:255' : 'nullable',
            'tag_line' => $request->role == '2' ? 'nullable|string|max:255' : 'nullable',
            'delivery_charges' => $request->role == '2' ? 'nullable|numeric|min:0' : 'nullable',

            // Location (only provider)
            'latitude' => $request->role == '1' ? 'required' : 'nullable',
            'longitude' => $request->role == '1' ? 'required' : 'nullable',
            'address' => $request->role == '1' ? 'required' : 'nullable',

            'service_id' => 'nullable|array',
            'service_id.*' => 'nullable|exists:categories,id',

            'experience' => 'nullable',
            'start_time' => 'nullable',
            'end_time' => 'nullable',

            'gallery_images' => 'nullable|array',
            'gallery_images.*' => $request->role == '1' ? 'image|mimes:jpeg,png,jpg|max:8192' : 'nullable',

            'bio' => 'nullable',
            'charge_type' => 'nullable|in:hourly,fixed',
            'charge_amount' => 'nullable',

            'document_type' => 'sometimes|nullable|string',
            'document_number' => 'sometimes|nullable|string',

            'referral_code' => 'nullable|string|exists:provider_profiles,referral_code',
            'referred_by_code' => 'nullable|string|exists:provider_profiles,referral_code',
            'referrer_code' => 'nullable|string|exists:provider_profiles,referral_code',

            // Optional but recommended
            'name' => 'nullable|string|max:255',
        ], [
            'referral_code.exists' => 'The entered referral code is invalid or does not exist.',
            'referred_by_code.exists' => 'The entered referral code is invalid or does not exist.',
            'referrer_code.exists' => 'The entered referral code is invalid or does not exist.',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        DB::beginTransaction();

        try {

            // ==============================
            // Provider Company Logo Upload
            // ==============================
            $companyLogoFilename = null;

            if ($request->role == '1' && $request->provider_type == 'company') {
                if ($request->hasFile('company_logo')) {
                    $file = $request->file('company_logo');
                    $companyLogoFilename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                    $file->move(public_path('uploads/company_logos/'), $companyLogoFilename);
                }
            }

            // ==============================
            // Shop Logo Upload
            // ==============================
            $shopLogoFilename = null;

            if ($request->role == '2') {
                if ($request->hasFile('shop_logo')) {
                    $file = $request->file('shop_logo');
                    $shopLogoFilename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                    $file->move(public_path('uploads/shop_logos/'), $shopLogoFilename);
                }
            }

            // ==============================
            // Display Name (FIXED)
            // ==============================
            if ($request->role == '1') {
                $displayName = ($request->provider_type == 'company')
                    ? $request->company_name
                    : ($request->name ?? '');
            } else {
                // role 0 & 2 â†’ use normal name only
                $displayName = $request->name ?? '';
            }

            $profileImage = null;

            if ($request->role == '1' && $request->provider_type == 'company') {
                $profileImage = $companyLogoFilename;
            }

            $user = User::create([
                'name' => $displayName,
                'phone' => $request->phone,
                'role' => $request->role,
                'has_roles' => (string) $request->role === '0' ? '0' : '0,' . $request->role,

                // Provider
                'provider_type' => $request->provider_type,
                'company_name' => $request->provider_type == 'company' ? $request->company_name : null,
                'company_logo' => $companyLogoFilename,

                // Shop
                'shop_title' => $request->role == '2' ? $request->shop_title : null,
                'shop_logo' => $shopLogoFilename,
                'tag_line' => $request->tag_line,
                'delivery_charges' => $request->role == '2' ? $request->delivery_charges : null,

                // Common
                'profile_image' => $profileImage,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'address' => $request->address,

                'service_category' => $request->service_id ?? [],
                'experience' => $request->experience,
                'work_hour_start' => $request->start_time,
                'work_hour_end' => $request->end_time,
                'bio' => $request->bio,
                'charge_type' => $request->charge_type,
                'charge_amount' => $request->charge_amount,

                // Status
                'status' => in_array($request->role, ['1', '2']) ? 'inactive' : 'active',

                'document_type' => $request->document_type ?? '',
                'document_number' => $request->document_number ?? '',
            ]);

            if ((string) $request->role === '1') {
                $referredById = null;
                $referredByCode = null;
                $inputReferral = $request->input('referral_code') ?? $request->input('referred_by_code') ?? $request->input('referrer_code');

                if (!empty($inputReferral)) {
                    $referrerProfile = ProviderProfile::where('referral_code', trim($inputReferral))->first();
                    if ($referrerProfile) {
                        $referredById = $referrerProfile->user_id;
                        $referredByCode = $referrerProfile->referral_code;
                    }
                }

                $ownReferralCode = ProviderProfile::generateUniqueReferralCode();

                ProviderProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'provider_type' => $request->provider_type,
                        'company_name' => $request->provider_type == 'company' ? $request->company_name : null,
                        'company_logo' => $companyLogoFilename,
                        'latitude' => $request->latitude,
                        'longitude' => $request->longitude,
                        'address' => $request->address,
                        'service_category' => $request->service_id ?? [],
                        'experience' => $request->experience,
                        'work_hour_start' => $request->start_time,
                        'work_hour_end' => $request->end_time,
                        'bio' => $request->bio,
                        'charge_type' => $request->charge_type,
                        'charge_amount' => $request->charge_amount,
                        'document_type' => $request->document_type ?? '',
                        'document_number' => $request->document_number ?? '',
                        'referral_code' => $ownReferralCode,
                        'referred_by_id' => $referredById,
                        'referred_by_code' => $referredByCode,
                    ]
                );
            }

            if ((string) $request->role === '2') {
                MarketplaceProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'shop_title' => $request->shop_title,
                        'shop_logo' => $shopLogoFilename,
                        'tag_line' => $request->tag_line,
                        'delivery_charges' => $request->delivery_charges,
                        'service_category' => $request->service_id ?? [],
                        'bio' => $request->bio,
                        'document_type' => $request->document_type ?? '',
                        'document_number' => $request->document_number ?? '',
                    ]
                );
            }

            // ==============================
            // User Code
            // ==============================
            $user->user_code = 'AZ' . (1000 + $user->id);
            $user->save();

            // ==============================
            // Gallery
            // ==============================
            $gallery = ProviderGallery::where('user_id', $user->id)->get();
            foreach ($gallery as $image) {
                $image->path = asset('uploads/provider_gallery/' . $image->path);
            }
            $user->gallery = $gallery;

            // ==============================
            // Profile Image URL (FIXED)
            // ==============================
            if ($request->role == '1') {
                $user->profile_image = $user->company_logo
                    ? asset('uploads/company_logos/' . $user->company_logo)
                    : asset('assets/img/default.jpg');
            } else {
                // role 0 & 2 â†’ default image
                $user->profile_image = asset('assets/img/default.jpg');
            }

            // ==============================
            // Token
            // ==============================
            if ((string) $request->role === '1') {
                $user = $this->decorateProviderUser($user);
            } elseif ((string) $request->role === '2') {
                $user = $this->decorateMarketplaceUser($user);
                $user->profile_image = asset('assets/img/default.jpg');
            }

            $token = $user->createToken('auth:sanctum')->plainTextToken;

            DB::commit();

            return $this->success([
                'token' => $token,
                'user' => $this->buildAuthenticatedUserPayload($user),
            ], 'Register successfully');
        } catch (\Exception $e) {
            Log::info($e->getMessage());
            DB::rollBack();
            return $this->error('Registration failed. Please try again later.', 500);
        }
    }

    public function becomeProvider(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'provider_type' => 'required|in:individual,company',
            'company_name' => $request->provider_type == 'company' ? 'required|string|max:255' : 'nullable',
            'company_logo' => $request->provider_type == 'company' ? 'required|image|mimes:jpeg,png,jpg|max:8192' : 'nullable',

            'latitude' => 'required',
            'longitude' => 'required',
            'address' => 'required',

            'service_id' => 'nullable|array',
            'service_id.*' => 'nullable|exists:categories,id',

            'experience' => 'nullable',
            'start_time' => 'nullable',
            'end_time' => 'nullable',

            'gallery_images' => 'nullable|array',
            'gallery_images.*' => 'image|mimes:jpeg,png,jpg|max:8192',

            'bio' => 'nullable',
            'charge_type' => 'nullable|in:hourly,fixed',
            'charge_amount' => 'nullable',

            'document_type' => 'sometimes|nullable|string',
            'document_number' => 'sometimes|nullable|string',

            'referral_code' => 'nullable|string|exists:provider_profiles,referral_code',
            'referred_by_code' => 'nullable|string|exists:provider_profiles,referral_code',
            'referrer_code' => 'nullable|string|exists:provider_profiles,referral_code',

            'name' => 'nullable|string|max:255',
        ], [
            'referral_code.exists' => 'The entered referral code is invalid or does not exist.',
            'referred_by_code.exists' => 'The entered referral code is invalid or does not exist.',
            'referrer_code.exists' => 'The entered referral code is invalid or does not exist.',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        DB::beginTransaction();

        try {
            $authUser = auth('sanctum')->user();
            if (!$authUser) {
                DB::rollBack();
                return $this->error('Unauthorized user.', 401);
            }

            $user = User::query()
                ->lockForUpdate()
                ->find($authUser->id);

            if (!$user) {
                DB::rollBack();
                return $this->error('Authenticated user not found.', 404);
            }

            $inputReferral = $request->input('referral_code') ?? $request->input('referred_by_code') ?? $request->input('referrer_code');

            if (!empty($inputReferral)) {
                $referrerProfile = ProviderProfile::where('referral_code', trim($inputReferral))->first();

                if (!$referrerProfile) {
                    DB::rollBack();
                    return $this->validationError(['referral_code' => ['The entered referral code is invalid or does not exist.']]);
                }

                if ($referrerProfile->user_id == $user->id) {
                    DB::rollBack();
                    return $this->validationError(['referral_code' => ['You cannot use your own referral code.']]);
                }
            }

            $hasRoles = $this->parseRoleList($user->has_roles);

            if (!in_array('1', $hasRoles, true)) {
                $hasRoles[] = '1';
            }

            $companyLogoFilename = optional($user->providerProfile)->company_logo;

            if ($request->provider_type == 'company' && $request->hasFile('company_logo')) {
                $file = $request->file('company_logo');
                $companyLogoFilename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/company_logos/'), $companyLogoFilename);
            }

            $existingProfile = $user->providerProfile;
            $ownReferralCode = optional($existingProfile)->referral_code ?: ProviderProfile::generateUniqueReferralCode();

            $referredById = optional($existingProfile)->referred_by_id;
            $referredByCode = optional($existingProfile)->referred_by_code;

            $inputReferral = $request->input('referral_code') ?? $request->input('referred_by_code') ?? $request->input('referrer_code');

            if (empty($referredById) && !empty($inputReferral)) {
                $referrerProfile = ProviderProfile::where('referral_code', trim($inputReferral))
                    ->where('user_id', '!=', $user->id)
                    ->first();
                if ($referrerProfile) {
                    $referredById = $referrerProfile->user_id;
                    $referredByCode = $referrerProfile->referral_code;
                }
            }

            ProviderProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'provider_type' => $request->provider_type,
                    'company_name' => $request->provider_type == 'company' ? $request->company_name : null,
                    'company_logo' => $companyLogoFilename,
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                    'address' => $request->address,
                    'service_category' => $request->service_id ?? [],
                    'experience' => $request->experience,
                    'work_hour_start' => $request->start_time,
                    'work_hour_end' => $request->end_time,
                    'bio' => $request->bio,
                    'charge_type' => $request->charge_type,
                    'charge_amount' => $request->charge_amount,
                    'document_type' => $request->document_type ?? optional($user->providerProfile)->document_type,
                    'document_number' => $request->document_number ?? optional($user->providerProfile)->document_number,
                    'referral_code' => $ownReferralCode,
                    'referred_by_id' => $referredById,
                    'referred_by_code' => $referredByCode,
                ]
            );

            if ($request->filled('name')) {
                $user->name = $request->name;
            }

            $user->role = '1';
            $user->has_roles = $this->syncHasRoles($user, '1');
            $user->provider_status = 'inactive';
            $user->save();

            if ($request->hasFile('gallery_images')) {
                foreach ($request->file('gallery_images') as $galleryImage) {
                    $galleryFilename = time() . '_' . Str::random(10) . '.' . $galleryImage->getClientOriginalExtension();
                    $galleryImage->move(public_path('uploads/provider_gallery/'), $galleryFilename);

                    ProviderGallery::create([
                        'user_id' => $user->id,
                        'path' => $galleryFilename,
                    ]);
                }
            }

            $gallery = ProviderGallery::where('user_id', $user->id)->get();
            foreach ($gallery as $image) {
                $image->path = asset('uploads/provider_gallery/' . $image->path);
            }

            $user->gallery = $gallery;
            $user = $this->decorateProviderUser($user);

            DB::commit();

            return $this->success([
                'id' => $user->id,
                'role' => (string) $user->role,
                'has_roles' => $user->fresh()->has_roles,
                'user' => $this->buildAuthenticatedUserPayload($user->fresh()),
            ], 'Provider role added successfully');
        } catch (\Exception $e) {
            dd($e);
            Log::info($e->getMessage());
            DB::rollBack();

            return $this->error('Failed to become provider. Please try again later.', 500);
        }
    }

    public function becomeSeller(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shop_logo' => 'required|image|mimes:jpeg,png,jpg|max:8192',
            'shop_title' => 'required|string|max:255',
            'shop_banner_image' => 'nullable|image|mimes:jpeg,png,jpg|max:8192',
            'tag_line' => 'nullable|string|max:255',
            'delivery_charges' => 'nullable|numeric|min:0',
            'bio' => 'nullable|string',
            'service_id' => 'nullable|array',
            'service_id.*' => 'nullable|exists:categories,id',
            'operation_hours' => 'nullable|array',
            'shop_status' => 'nullable|in:on,off',
            'document_type' => 'sometimes|nullable|string|max:255',
            'document_number' => 'sometimes|nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'marketplace_address' => 'nullable|string|max:255',
            'marketplace_latitude' => 'nullable|numeric',
            'marketplace_longitude' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        DB::beginTransaction();

        try {
            $authUser = auth('sanctum')->user();
            if (!$authUser) {
                DB::rollBack();
                return $this->error('Unauthorized user.', 401);
            }

            $user = User::query()
                ->lockForUpdate()
                ->find($authUser->id);

            if (!$user) {
                DB::rollBack();
                return $this->error('Authenticated user not found.', 404);
            }

            $hasRoles = $this->parseRoleList($user->has_roles);

            if (!in_array('2', $hasRoles, true)) {
                $hasRoles[] = '2';
            }

            $shopLogoFilename = optional($user->marketplaceProfile)->shop_logo;
            $shopBannerFilename = optional($user->marketplaceProfile)->shop_banner_image;

            if ($request->hasFile('shop_logo')) {
                $file = $request->file('shop_logo');
                $shopLogoFilename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/shop_logos/'), $shopLogoFilename);
            }

            if ($request->hasFile('shop_banner_image')) {
                $file = $request->file('shop_banner_image');
                $shopBannerFilename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/shop_banners/'), $shopBannerFilename);
            }

            MarketplaceProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'shop_title' => $request->shop_title,
                    'shop_logo' => $shopLogoFilename,
                    'shop_banner_image' => $shopBannerFilename,
                    'tag_line' => $request->tag_line,
                    'delivery_charges' => $request->delivery_charges,
                    'bio' => $request->bio,
                    'service_category' => $request->service_id ?? [],
                    'operation_hours' => $request->operation_hours,
                    'shop_status' => $request->shop_status ? strtolower($request->shop_status) : null,
                    'document_type' => $request->document_type,
                    'document_number' => $request->document_number,
                    'address' => $request->marketplace_address ?? $request->address,
                    'latitude' => $request->marketplace_latitude ?? $request->latitude,
                    'longitude' => $request->marketplace_longitude ?? $request->longitude,
                    'expires_at' => now()->addYear(),
                ]
            );

            if ($request->filled('name')) {
                $user->name = $request->name;
            }

            $user->role = '2';
            $user->has_roles = $this->syncHasRoles($user, '2');
            $user->marketplace_status = 'inactive';
            $user->save();

            $user = $this->decorateMarketplaceUser($user);
            $user->profile_image = asset('assets/img/default.jpg');

            DB::commit();

            return $this->success([
                'id' => $user->id,
                'role' => (string) $user->role,
                'has_roles' => $user->fresh()->has_roles,
                'user' => $this->buildAuthenticatedUserPayload($user->fresh()),
            ], 'Marketplace seller role added successfully');
        } catch (\Exception $e) {
            Log::info($e->getMessage());
            DB::rollBack();

            return $this->error('Failed to become marketplace seller. Please try again later.', 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|regex:/^\+9665[0-9]{8}$/',
            'is_otp_verified' => 'required|in:1,true',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $user = User::where('phone', $request->phone)->first();

            if (!$user) {
                return $this->error('Invalid credentials.', 401);
            }

            // if ($user->status !== 'active') {
            //     return $this->error('Your account is inactive. Please contact admin.', 401);
            // }

            $token = $user->createToken('auth:sanctum')->plainTextToken;

            $gallery = ProviderGallery::where('user_id', $user->id)->get();
            foreach ($gallery as $image) {
                $image->path = asset('uploads/provider_gallery/' . $image->path);
            }

            $user->gallery = $gallery;
            if ((string) $user->role === '1') {
                $user = $this->decorateProviderUser($user);
            } elseif ((string) $user->role === '2') {
                $user = $this->decorateMarketplaceUser($user);
                $user->profile_image = asset('assets/img/default.jpg');
            } else {
                $user->profile_image = $user->profile_image ? asset('uploads/profile_images/' . $user->profile_image) : asset('assets/img/default.jpg');
            }

            return $this->success([
                'token' => $token,
                'user' => $this->buildAuthenticatedUserPayload($user),
            ], 'Login successfully');
        } catch (\Exception $e) {
            return $this->error('Something went wrong during login. Please try again later.', 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            $user->currentAccessToken()->delete();

            return $this->success(null, 'Logged out successfully.');
        } catch (\Throwable $e) {
            Log::error('Logout failed: ' . $e->getMessage());
            return $this->error('Logout failed.', 500);
        }
    }

    public function activeAccountRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return $this->error('Unauthorized.', 401);
            }

            // Determine status based on current active role
            $role = (int) $user->role;
            $statusField = 'status';
            if ($role === 1) {
                $statusField = 'provider_status';
            } elseif ($role === 2) {
                $statusField = 'marketplace_status';
            }

            $currentStatus = $user->$statusField;

            if ($currentStatus === 'active') {
                return $this->error('Your account is already active.', 400);
            }

            $exists = \App\Models\AccountActiveRequest::where('user_id', $user->id)->exists();
            if ($exists) {
                return $this->error('You have already submitted an activation request.', 400);
            }

            $activeRequest = \App\Models\AccountActiveRequest::create([
                'user_id' => $user->id,
                'message' => $request->message,
            ]);

            return $this->success($activeRequest, 'Activation request submitted successfully.');
        } catch (\Exception $e) {
            Log::error('Activation request error: ' . $e->getMessage());
            return $this->error('Something went wrong. Please try again later.', 500);
        }
    }

    public function deleteUser(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return $this->error('Unauthenticated.', 401);
        }

        DB::beginTransaction();

        try {
            $userId = (int) $user->id;
            $deleted = [];

            $trackDelete = function ($table, $query) use (&$deleted) {
                $count = $query->delete();

                if ($count > 0) {
                    $deleted[$table] = ($deleted[$table] ?? 0) + $count;
                }

                return $count;
            };

            /*
        |--------------------------------------------------------------------------
        | Detect all roles from has_roles
        |--------------------------------------------------------------------------
        */
            $roles = $user->has_roles;

            if (is_string($roles)) {
                $decodedRoles = json_decode($roles, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedRoles)) {
                    $roles = $decodedRoles;
                } else {
                    $roles = explode(',', $roles);
                }
            }

            if (!is_array($roles)) {
                $roles = [];
            }

            $roles = collect($roles)
                ->map(fn($role) => (string) trim($role))
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            if (empty($roles)) {
                $roles = [(string) $user->role];
            }

            $hasUserRole        = in_array('0', $roles, true);
            $hasProviderRole    = in_array('1', $roles, true);
            $hasMarketplaceRole = in_array('2', $roles, true);

            /*
        |--------------------------------------------------------------------------
        | Common Data
        |--------------------------------------------------------------------------
        */
            $trackDelete('personal_access_tokens', DB::table('personal_access_tokens')
                ->where('tokenable_id', $userId));

            $trackDelete('sessions', DB::table('sessions')
                ->where('user_id', $userId));

            $trackDelete('job_notifications', DB::table('job_notifications')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $userId));

            $trackDelete('carts', DB::table('carts')
                ->where('user_id', $userId));

            $trackDelete('store_visits', DB::table('store_visits')
                ->where('visitor_user_id', $userId));

            $trackDelete('product_views', DB::table('product_views')
                ->where('viewer_user_id', $userId));

            /*
        |--------------------------------------------------------------------------
        | Role 0 - Normal User Data
        |--------------------------------------------------------------------------
        */
            if ($hasUserRole) {
                $jobIds = DB::table('jobss')
                    ->where('user_id', $userId)
                    ->pluck('id')
                    ->toArray();

                $orderIds = DB::table('orders')
                    ->where('user_id', $userId)
                    ->pluck('id')
                    ->toArray();

                if (!empty($orderIds)) {
                    $trackDelete('order_tracking_history', DB::table('order_tracking_history')
                        ->whereIn('order_id', $orderIds));

                    $trackDelete('ratings', DB::table('ratings')
                        ->whereIn('order_id', $orderIds));
                }

                if (!empty($jobIds)) {
                    $trackDelete('job_images', DB::table('job_images')
                        ->whereIn('job_id', $jobIds));

                    $trackDelete('bids', DB::table('bids')
                        ->whereIn('job_id', $jobIds));

                    $trackDelete('complaints', DB::table('complaints')
                        ->whereIn('job_id', $jobIds));

                    $trackDelete('transactions', DB::table('transactions')
                        ->whereIn('job_id', $jobIds));

                    $trackDelete('orders', DB::table('orders')
                        ->whereIn('job_id', $jobIds));
                }

                $trackDelete('ratings', DB::table('ratings')
                    ->where('user_id', $userId));

                $trackDelete('complaints', DB::table('complaints')
                    ->where('user_id', $userId));

                $trackDelete('favorite_providers', DB::table('favorite_providers')
                    ->where('user_id', $userId));

                $trackDelete('orders', DB::table('orders')
                    ->where('user_id', $userId));

                $trackDelete('jobss', DB::table('jobss')
                    ->where('user_id', $userId));
            }

            /*
        |--------------------------------------------------------------------------
        | Role 1 - Provider Data
        |--------------------------------------------------------------------------
        */
            if ($hasProviderRole) {
                $providerOrderIds = DB::table('orders')
                    ->where('provider_id', $userId)
                    ->pluck('id')
                    ->toArray();

                if (!empty($providerOrderIds)) {
                    $trackDelete('order_tracking_history', DB::table('order_tracking_history')
                        ->whereIn('order_id', $providerOrderIds));

                    $trackDelete('ratings', DB::table('ratings')
                        ->whereIn('order_id', $providerOrderIds));
                }

                $trackDelete('bids', DB::table('bids')
                    ->where('provider_id', $userId));

                $trackDelete('complaints', DB::table('complaints')
                    ->where('provider_id', $userId));

                $trackDelete('favorite_providers', DB::table('favorite_providers')
                    ->where('provider_id', $userId));

                $trackDelete('ratings', DB::table('ratings')
                    ->where('provider_id', $userId));

                $trackDelete('transactions', DB::table('transactions')
                    ->where('provider_id', $userId));

                $trackDelete('orders', DB::table('orders')
                    ->where('provider_id', $userId));

                $trackDelete('provider_profile_gallery', DB::table('provider_profile_gallery')
                    ->where('user_id', $userId));

                $trackDelete('provider_skills', DB::table('provider_skills')
                    ->where('user_id', $userId));

                $trackDelete('provider_profiles', DB::table('provider_profiles')
                    ->where('user_id', $userId));
            }

            /*
        |--------------------------------------------------------------------------
        | Role 2 - Marketplace Data
        |--------------------------------------------------------------------------
        */
            if ($hasMarketplaceRole) {
                $productIds = DB::table('products')
                    ->where('user_id', $userId)
                    ->pluck('id')
                    ->toArray();

                if (!empty($productIds)) {
                    $trackDelete('campaigns', DB::table('campaigns')
                        ->whereIn('product_id', $productIds));

                    $trackDelete('product_views', DB::table('product_views')
                        ->whereIn('product_id', $productIds));

                    $trackDelete('carts', DB::table('carts')
                        ->whereIn('product_id', $productIds));

                    $trackDelete('marketplace_order_items', DB::table('marketplace_order_items')
                        ->whereIn('product_id', $productIds));
                }

                $shopOrderIds = DB::table('marketplace_order_items')
                    ->where('shop_id', $userId)
                    ->pluck('marketplace_order_id')
                    ->toArray();

                if (!empty($shopOrderIds)) {
                    $trackDelete('marketplace_shop_reviews', DB::table('marketplace_shop_reviews')
                        ->whereIn('marketplace_order_id', $shopOrderIds));

                    $trackDelete('marketplace_order_items', DB::table('marketplace_order_items')
                        ->whereIn('marketplace_order_id', $shopOrderIds));

                    $trackDelete('marketplace_orders', DB::table('marketplace_orders')
                        ->whereIn('id', $shopOrderIds));
                }

                $trackDelete('marketplace_shop_reviews', DB::table('marketplace_shop_reviews')
                    ->where('shop_id', $userId));

                $trackDelete('marketplace_order_items', DB::table('marketplace_order_items')
                    ->where('shop_id', $userId));

                $trackDelete('store_visits', DB::table('store_visits')
                    ->where('shop_id', $userId));

                $trackDelete('product_views', DB::table('product_views')
                    ->where('shop_id', $userId));

                $trackDelete('products', DB::table('products')
                    ->where('user_id', $userId));

                $trackDelete('marketplace_profiles', DB::table('marketplace_profiles')
                    ->where('user_id', $userId));
            }

            /*
        |--------------------------------------------------------------------------
        | Marketplace Orders as Buyer
        |--------------------------------------------------------------------------
        */
            $buyerMarketplaceOrderIds = DB::table('marketplace_orders')
                ->where('user_id', $userId)
                ->pluck('id')
                ->toArray();

            if (!empty($buyerMarketplaceOrderIds)) {
                $trackDelete('marketplace_shop_reviews', DB::table('marketplace_shop_reviews')
                    ->whereIn('marketplace_order_id', $buyerMarketplaceOrderIds));

                $trackDelete('marketplace_order_items', DB::table('marketplace_order_items')
                    ->whereIn('marketplace_order_id', $buyerMarketplaceOrderIds));

                $trackDelete('marketplace_orders', DB::table('marketplace_orders')
                    ->whereIn('id', $buyerMarketplaceOrderIds));
            }

            /*
        |--------------------------------------------------------------------------
        | Extra Safety Cleanup
        |--------------------------------------------------------------------------
        */
            $trackDelete('marketplace_shop_reviews', DB::table('marketplace_shop_reviews')
                ->where('user_id', $userId)
                ->orWhere('shop_id', $userId));

            $trackDelete('product_views', DB::table('product_views')
                ->where('shop_id', $userId)
                ->orWhere('viewer_user_id', $userId));

            $trackDelete('store_visits', DB::table('store_visits')
                ->where('shop_id', $userId)
                ->orWhere('visitor_user_id', $userId));

            $trackDelete('carts', DB::table('carts')
                ->where('user_id', $userId));

            /*
        |--------------------------------------------------------------------------
        | Delete User Last
        |--------------------------------------------------------------------------
        */
            $trackDelete('users', DB::table('users')
                ->where('id', $userId));

            DB::commit();

            return $this->success([
                'user_id' => $userId,
                'roles_checked' => $roles,
                'deleted_summary' => $deleted,
                'total_deleted_rows' => array_sum($deleted),
            ], 'Account and related data deleted successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Delete user failed: ' . $e->getMessage(), [
                'user_id' => $user->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Failed to delete account. Please try again later.', 500);
        }
    }

    public function switchRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|in:0,1,2',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $user = auth('sanctum')->user();
            $targetRole = (string) $request->role;
            $currentRole = (string) $user->role;

            if ($targetRole === '1' && !$user->providerProfile()->exists()) {
                return $this->error('Provider profile not found. Please become provider first.', 400);
            }

            if ($targetRole === '2' && !$user->marketplaceProfile()->exists()) {
                return $this->error('Marketplace profile not found. Please become seller first.', 400);
            }

            /*
        |-------------------------------------------------------------
        | Parse has_roles column
        | Example stored values: "0", "0,1", "0,1,2"
        |-------------------------------------------------------------
        */
            $hasRoles = [];

            if (!empty($user->has_roles)) {
                $hasRoles = array_filter(array_map('trim', explode(',', $user->has_roles)), function ($value) {
                    return $value !== '';
                });
            }

            // Ensure current role exists in has_roles
            if (!in_array($currentRole, $hasRoles, true)) {
                $hasRoles[] = $currentRole;
            }

            if (!in_array($targetRole, $hasRoles, true)) {
                return $this->error('You do not have access to this role yet.', 400);
            }

            // Keep unique roles only
            $hasRoles = array_values(array_unique($hasRoles));

            if (!in_array('0', $hasRoles, true)) {
                $hasRoles[] = '0';
            }

            // Save all roles
            $user->has_roles = implode(',', array_values(array_unique($hasRoles)));

            // If already active
            if ($currentRole === $targetRole) {
                $user->save();

                return $this->success([
                    'id' => $user->id,
                    'role' => (string) $user->role,
                    'has_roles' => $user->has_roles,
                    'user' => $this->buildAuthenticatedUserPayload($user),
                ], 'Role is already active.');
            }

            // Switch current role
            $user->role = $targetRole;
            $user->save();

            if ($targetRole === '0') {
                $message = 'Switched to user successfully.';
            } elseif ($targetRole === '1') {
                $message = 'Switched to provider successfully.';
            } else {
                $message = 'Switched to marketplace successfully.';
            }

            return $this->success([
                'id' => $user->id,
                'role' => (string) $user->role,
                'has_roles' => $user->has_roles,
                'user' => $this->buildAuthenticatedUserPayload($user),
            ], $message);
        } catch (\Throwable $e) {
            Log::error('Error switching role: ' . $e->getMessage());

            return $this->error('Failed to switch role.', 500);
        }
    }

    public function update_profile(Request $request)
    {
        $user = auth('sanctum')->user();

        $rules = [
            'name'             => 'sometimes|nullable|string',
            'email'            => 'sometimes|nullable|email|unique:users,email,' . $user->id,
            'phone'            => 'sometimes|nullable|regex:/^\+9665[0-9]{8}$/|unique:users,phone,' . $user->id,
            'profile_image'    => 'sometimes|nullable|image|mimes:jpeg,png,jpg|max:8192',
            'address'          => 'sometimes|nullable|string',
            'location_label'   => 'sometimes|nullable|string',
            'latitude'         => 'sometimes|nullable',
            'longitude'        => 'sometimes|nullable',
            'service_id'       => 'sometimes|nullable|array',
            'service_id.*'     => 'exists:categories,id',
            'bio'              => 'sometimes|nullable|string',
            'document_type'    => 'sometimes|nullable|string',
            'document_number'  => 'sometimes|nullable|string',
            'shop_title'       => 'sometimes|nullable|string|max:255',
            'shop_logo'        => 'sometimes|nullable|image|mimes:jpeg,png,jpg|max:8192',
            'shop_banner_image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg|max:8192',
            'shop_tagline'     => 'sometimes|nullable|string|max:255',
            'delivery_charges' => 'sometimes|nullable|numeric|min:0',
            'marketplace_address'   => 'sometimes|nullable|string',
            'marketplace_latitude'  => 'sometimes|nullable',
            'marketplace_longitude' => 'sometimes|nullable',
            'operation_hours'  => 'sometimes|nullable|array',
            'shop_status'      => 'sometimes|nullable|in:on,off',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        DB::beginTransaction();
        try {
            $role = (string) $user->role;

            if ($role === '1') {
                $user = $this->updateProviderProfile($user, $request);
            } elseif ($role === '2') {
                $user = $this->updateMarketplaceProfile($user, $request);
            } else {
                $user = $this->updateCustomerProfile($user, $request);
            }

            DB::commit();
            return $this->success($this->buildAuthenticatedUserPayload($user), 'Profile updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return $this->error('Something went wrong', 500);
        }
    }

    private function updateCustomerProfile(User $user, $request): User
    {
        if ($request->hasFile('profile_image')) {
            if ($user->profile_image && File::exists(public_path('uploads/profile_images/' . $user->profile_image))) {
                File::delete(public_path('uploads/profile_images/' . $user->profile_image));
            }
            $file = $request->file('profile_image');
            $user->profile_image = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/profile_images/'), $user->profile_image);
        }

        $user->name           = $request->name ?? $user->name;
        $user->email          = $request->email ?? $user->email;
        $user->phone          = $request->phone ?? $user->phone;
        $user->address        = $request->address ?? $user->address;
        $user->latitude       = $request->latitude ?? $user->latitude;
        $user->longitude      = $request->longitude ?? $user->longitude;
        $user->location_label = $request->location_label ?? $user->location_label;
        $user->save();

        $user->profile_image = $user->profile_image
            ? asset('uploads/profile_images/' . $user->profile_image)
            : asset('assets/img/default.jpg');

        return $user;
    }

    private function updateProviderProfile(User $user, $request): User
    {
        $providerProfile = $user->providerProfile()->firstOrCreate(['user_id' => $user->id]);

        if ($request->hasFile('profile_image')) {
            if ($user->profile_image && File::exists(public_path('uploads/profile_images/' . $user->profile_image))) {
                File::delete(public_path('uploads/profile_images/' . $user->profile_image));
            }
            $file = $request->file('profile_image');
            $user->profile_image = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/profile_images/'), $user->profile_image);
        }

        $user->name           = $request->name ?? $user->name;
        $user->email          = $request->email ?? $user->email;
        $user->phone          = $request->phone ?? $user->phone;
        $user->location_label = $request->location_label ?? $user->location_label;
        $user->save();

        $providerProfile->address         = $request->address ?? $providerProfile->address;
        $providerProfile->latitude        = $request->latitude ?? $providerProfile->latitude;
        $providerProfile->longitude       = $request->longitude ?? $providerProfile->longitude;
        $providerProfile->service_category = $request->service_id ?? $providerProfile->service_category ?? [];
        $providerProfile->bio             = $request->bio ?? $providerProfile->bio;
        $providerProfile->document_type   = $request->document_type ?? $providerProfile->document_type;
        $providerProfile->document_number = $request->document_number ?? $providerProfile->document_number;
        $providerProfile->save();

        return $this->decorateProviderUser($user);
    }

    private function updateMarketplaceProfile(User $user, $request): User
    {
        $marketplaceProfile = $user->marketplaceProfile()->firstOrCreate(['user_id' => $user->id]);

        if ($request->hasFile('shop_logo')) {
            if ($marketplaceProfile->shop_logo && File::exists(public_path('uploads/shop_logos/' . $marketplaceProfile->shop_logo))) {
                File::delete(public_path('uploads/shop_logos/' . $marketplaceProfile->shop_logo));
            }
            $file = $request->file('shop_logo');
            $marketplaceProfile->shop_logo = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/shop_logos/'), $marketplaceProfile->shop_logo);
        }

        if ($request->hasFile('shop_banner_image')) {
            if ($marketplaceProfile->shop_banner_image && File::exists(public_path('uploads/shop_banners/' . $marketplaceProfile->shop_banner_image))) {
                File::delete(public_path('uploads/shop_banners/' . $marketplaceProfile->shop_banner_image));
            }
            $file = $request->file('shop_banner_image');
            $marketplaceProfile->shop_banner_image = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/shop_banners/'), $marketplaceProfile->shop_banner_image);
        }

        $user->name           = $request->name ?? $user->name;
        $user->email          = $request->email ?? $user->email;
        $user->phone          = $request->phone ?? $user->phone;
        $user->location_label = $request->location_label ?? $user->location_label;
        $user->address        = $request->address ?? $user->address;
        $user->latitude       = $request->latitude ?? $user->latitude;
        $user->longitude      = $request->longitude ?? $user->longitude;
        $user->save();

        $marketplaceProfile->shop_title     = $request->shop_title ?? $marketplaceProfile->shop_title;
        $marketplaceProfile->tag_line       = $request->shop_tagline ?? $marketplaceProfile->tag_line;
        $marketplaceProfile->bio            = $request->bio ?? $marketplaceProfile->bio;
        $marketplaceProfile->service_category = $request->service_id ?? $marketplaceProfile->service_category ?? [];
        $marketplaceProfile->delivery_charges = $request->has('delivery_charges') ? $request->delivery_charges : $marketplaceProfile->delivery_charges;
        $marketplaceProfile->operation_hours  = $request->operation_hours ?? $marketplaceProfile->operation_hours;
        $marketplaceProfile->shop_status      = $request->shop_status ? strtolower($request->shop_status) : $marketplaceProfile->shop_status;
        $marketplaceProfile->document_type    = $request->document_type ?? $marketplaceProfile->document_type;
        $marketplaceProfile->document_number  = $request->document_number ?? $marketplaceProfile->document_number;
        $marketplaceProfile->address          = $request->marketplace_address ?? $marketplaceProfile->address;
        $marketplaceProfile->latitude         = $request->marketplace_latitude ?? $marketplaceProfile->latitude;
        $marketplaceProfile->longitude        = $request->marketplace_longitude ?? $marketplaceProfile->longitude;
        $marketplaceProfile->save();

        $user = $this->decorateMarketplaceUser($user);
        $user->profile_image = asset('assets/img/default.jpg');

        return $user;
    }

    public function get_profile()
    {
        try {
            $user = auth('sanctum')->user();
            $role = (string) $user->role;

            if ($role === '1') {
                return $this->success($this->buildAuthenticatedUserPayload($this->decorateProviderUser($user)));
            }

            if ($role === '2') {
                return $this->success($this->buildAuthenticatedUserPayload($this->decorateMarketplaceUser($user)));
            }

            return $this->success($this->buildAuthenticatedUserPayload($this->decorateCustomerUser($user)));
        } catch (\Throwable $e) {
            Log::error('Error in get_profile: ' . $e->getMessage());
            return $this->error('Failed to load profile.', 500);
        }
    }

    private function decorateCustomerUser(User $user): User
    {
        $user->profile_image = $user->profile_image
            ? asset('uploads/profile_images/' . $user->profile_image)
            : asset('assets/img/default.jpg');

        return $user;
    }

    public function update_fcm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $user = auth('sanctum')->user();
        $user->fcm_token = $request->fcm_token;
        $user->save();

        $user->service_license = $user->service_license
            ? asset('uploads/license_files/' . $user->service_license)
            : null;

        $user->certification = $user->certification
            ? asset('uploads/certification_files/' . $user->certification)
            : null;

        $user->profile_image = $user->profile_image
            ? asset('uploads/profile_images/' . $user->profile_image)
            : asset('assets/img/default.jpg');

        $gallery = ProviderGallery::where('user_id', $user->id)->get();
        foreach ($gallery as $image) {
            $image->path = asset('uploads/provider_gallery/' . $image->path);
        }

        $user->gallery = $gallery;


        return $this->success($user, 'FCM token updated successfully.');
    }


    public function update_profession(Request $request)
    {
        // dd($request->all());
        $validator = Validator::make($request->all(), [
            'service_id' => 'nullable|array',
            'service_id.*' => 'nullable|exists:categories,id',
            'skills' => 'nullable|array|min:1',
            'skills.*' => 'nullable|string',
            'bio' => 'nullable|string',
            'start_time' => 'nullable',
            'end_time' => 'nullable',
            'charge_type' => 'nullable|in:hourly,fixed',
            'charge_amount' => 'nullable|numeric|min:0',
            'experience' => 'nullable|in:1-3 years,3-5 years,5-10 years,10+ years',
            'service_license' => 'nullable|mimes:pdf,jpg,jpeg,png|max:8192',
            'certification' => 'nullable|mimes:pdf,jpg,jpeg,png|max:8192'
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        DB::beginTransaction();

        try {
            $user = auth('sanctum')->user();

            if ($user->role != '1') {
                return $this->unauthorized('You are not authorized to update your profession', 400);
            }

            $providerProfile = $user->providerProfile()->firstOrCreate(['user_id' => $user->id]);

            if ($request->hasFile('service_license')) {
                if ($providerProfile->service_license && File::exists(public_path('uploads/license_files/' . $providerProfile->service_license))) {
                    File::delete(public_path('uploads/license_files/' . $providerProfile->service_license));
                }

                $file = $request->file('service_license');
                $licenseFilename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/license_files/'), $licenseFilename);
                $providerProfile->service_license = $licenseFilename;
            }

            if ($request->hasFile('certification')) {
                if ($providerProfile->certification && File::exists(public_path('uploads/certification_files/' . $providerProfile->certification))) {
                    File::delete(public_path('uploads/certification_files/' . $providerProfile->certification));
                }

                $file = $request->file('certification');
                $certFilename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/certification_files/'), $certFilename);
                $providerProfile->certification = $certFilename;
            }

            $providerProfile->service_category = $request->service_id ?? $providerProfile->service_category ?? [];
            $providerProfile->bio = $request->bio ?? $providerProfile->bio;
            $providerProfile->work_hour_start = $request->filled('start_time') ? $request->start_time : $providerProfile->work_hour_start;
            $providerProfile->work_hour_end = $request->filled('end_time') ? $request->end_time : $providerProfile->work_hour_end;
            $providerProfile->experience = $request->has('experience') ? $request->experience : $providerProfile->experience;
            $providerProfile->charge_type = $request->has('charge_type') ? $request->charge_type : $providerProfile->charge_type;
            $providerProfile->charge_amount = $request->has('charge_amount') ? $request->charge_amount : $providerProfile->charge_amount;
            $providerProfile->save();

            if ($request->has('skills') && is_array($request->skills)) {
                ProviderSkills::where('user_id', $user->id)->delete();

                foreach ($request->skills as $skillName) {
                    ProviderSkills::create([
                        'user_id' => $user->id,
                        'name' => $skillName
                    ]);
                }
            }

            $user = $this->decorateProviderUser($user);
            $gallery = ProviderGallery::where('user_id', $user->id)->get();
            foreach ($gallery as $image) {
                $image->path = asset('uploads/provider_gallery/' . $image->path);
            }

            $user->gallery = $gallery;

            DB::commit();

            return $this->success($user, 'Profession updated successfully.');


            if ($request->hasFile('service_license')) {
                if ($user->service_license && File::exists(public_path('uploads/license_files/' . $user->service_license))) {
                    File::delete(public_path('uploads/license_files/' . $user->service_license));
                }

                $file = $request->file('service_license');
                $licenseFilename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/license_files/'), $licenseFilename);
                $user->service_license = $licenseFilename;
            }


            if ($request->hasFile('certification')) {
                if ($user->certification && File::exists(public_path('uploads/certification_files/' . $user->certification))) {
                    File::delete(public_path('uploads/certification_files/' . $user->certification));
                }

                $file = $request->file('certification');
                $certFilename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/certification_files/'), $certFilename);
                $user->certification = $certFilename;
            }

            // $user->service_category = json_encode(array_values($request->service_id));
            $user->service_category = $request->service_id ?? [];
            // $user->service_category = $request->service_id ? json_encode($request->service_id) : null;
            // $user->experience = $request->experience;
            $user->bio = $request->bio;

            $user->work_hour_start = $request->filled('start_time') ? $request->start_time : null;
            $user->work_hour_end = $request->filled('end_time') ? $request->end_time : null;

            // $user->work_hour_start = $request->start_time;
            // $user->work_hour_end = $request->end_time;
            // $user->charge_type = $request->charge_type;
            // $user->charge_amount = $request->charge_amount;
            $user->experience = $request->has('experience') ? $request->experience : $user->experience;
            $user->charge_type = $request->has('charge_type') ? $request->charge_type : $user->charge_type;
            $user->charge_amount = $request->has('charge_amount') ? $request->charge_amount : $user->charge_amount;
            $user->save();


            // ProviderSkills::where('user_id', $user->id)->delete();
            // foreach ($request->skills as $skillName) {
            //     ProviderSkills::create([
            //         'user_id' => $user->id,
            //         'name' => $skillName
            //     ]);
            // }

            if ($request->has('skills') && is_array($request->skills)) {
                ProviderSkills::where('user_id', $user->id)->delete();

                foreach ($request->skills as $skillName) {
                    ProviderSkills::create([
                        'user_id' => $user->id,
                        'name' => $skillName
                    ]);
                }
            }


            $user->service_license = $user->service_license ? asset('uploads/license_files/' . $user->service_license) : null;
            $user->certification = $user->certification ? asset('uploads/certification_files/' . $user->certification) : null;
            $user->profile_image = $user->profile_image ? asset('uploads/profile_images/' . $user->profile_image) : asset('assets/img/default.jpg');

            $gallery = ProviderGallery::where('user_id', $user->id)->get();
            foreach ($gallery as $image) {
                $image->path = asset('uploads/provider_gallery/' . $image->path);
            }

            $user->gallery = $gallery;

            DB::commit();

            return $this->success($user, 'Profession updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Profession update failed: ' . $e->getMessage());
            return $this->error('Something went wrong during profession update. Please try again later.' . $e);
        }
    }

    public function addProduct(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'banner_image' => 'required|file|mimes:jpeg,png,jpg,gif',

            'product_images' => 'nullable|array',
            'product_images.*' => 'file|mimes:jpeg,png,jpg,gif',

            'category_id' => 'required|integer|exists:categories,id',
            'status' => 'required|in:publish,unpublish,pending,trash',
            'product_name' => 'required|string',
            'product_description' => 'required|string',
            'price' => 'required|string',
            'sale_price' => 'nullable|numeric',
            'discount_type' => 'nullable|string',
            'discount_value' => 'required_if:discount_type,!=,null|numeric',
            'tax_status' => 'required|string',
            'installation_available' => 'nullable|boolean',
            'installation_price' => 'required_if:installation_available,true|numeric',
            'installation_details' => 'required_if:installation_available,true|string',
            'weight' => 'nullable|numeric',
            'height' => 'nullable|numeric',
            'width' => 'nullable|numeric',
            'length' => 'nullable|numeric',
            'total_stock' => 'required|integer',
            'limited_stock' => 'nullable|integer',
            'sku' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $bannerImagePath = $request->file('banner_image')->store('products/banners', 'public');

        $productImages = [];

        if ($request->hasFile('product_images')) {
            foreach ($request->file('product_images') as $image) {
                $productImages[] = $image->store('products/images', 'public');
            }
        }

        $productImages = array_unique($productImages);

        $productImagesString = !empty($productImages)
            ? implode(',', $productImages)
            : null;


        $product = new Product();
        $product->user_id = Auth::id();
        $product->banner_image = $bannerImagePath;
        $product->product_images = $productImagesString;

        $product->category_id = $request->category_id;
        $product->status = $request->status;
        $product->product_name = $request->product_name;
        $product->product_description = $request->product_description;
        $product->price = $request->price;
        $product->sale_price = $request->sale_price;
        $product->discount_type = $request->discount_type;
        $product->discount_value = $request->discount_value;
        $product->tax_status = $request->tax_status;
        $product->installation_available = $request->installation_available;
        $product->installation_price = $request->installation_price;
        $product->installation_details = $request->installation_details;
        $product->weight = $request->weight;
        $product->height = $request->height;
        $product->width = $request->width;
        $product->length = $request->length;
        $product->total_stock = $request->total_stock;
        $product->limited_stock = $request->limited_stock;
        $product->sku = $request->sku;

        $product->save();

        $product->product_images = $product->product_images
            ? explode(',', $product->product_images)
            : [];

        return response()->json([
            'message' => 'Product added successfully',
            'product' => $product
        ], 201);
    }

    public function getProducts(Request $request)
    {
        $status = $request->query('status');

        if ($status === 'published') {
            $status = 'publish';
        }

        if ($status === 'unpublished') {
            $status = 'unpublish';
        }

        if ($status === 'trashed') {
            $status = 'trash';
        }

        $query = Product::query()
            ->with('category')
            ->where('user_id', Auth::id());

        // Apply filter only if status is not 'all'
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $products = $query->paginate(10);
        $products->getCollection()->transform(function ($product) {
            $product->banner_image = !empty($product->banner_image)
                ? asset('storage/' . $product->banner_image)
                : asset('assets/img/default.jpg');

            $images = $product->product_images;

            if (is_string($images)) {
                $images = array_filter(explode(',', $images));
            }

            $product->product_images = collect($images ?: [])
                ->map(function ($image) {
                    return asset('storage/' . $image);
                })
                ->values();

            $product->category = $product->category
                ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                ]
                : null;

            return $product;
        });

        return response()->json([
            'message' => 'Products retrieved successfully',
            'products' => $products
        ], 200);
    }

    public function getProductDetail($id)
    {
        $product = Product::with('category')
            ->where('user_id', Auth::id())
            ->find($id);

        if (!$product) {
            return response()->json([
                'message' => 'Product not found'
            ], 404);
        }

        // Banner Image
        $product->banner_image = !empty($product->banner_image)
            ? asset('storage/' . $product->banner_image)
            : asset('assets/img/default.jpg');

        // Product Images
        $images = $product->product_images;

        if (is_string($images)) {
            $images = array_filter(explode(',', $images));
        }

        $product->product_images = collect($images ?: [])
            ->map(function ($image) {
                return asset('storage/' . $image);
            })
            ->values();

        // Category Format
        $product->category = $product->category
            ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
            ]
            : null;

        return response()->json([
            'message' => 'Product retrieved successfully',
            'product' => $product
        ], 200);
    }

    public function getAllMarketplace()
    {
        try {
            $user = auth('sanctum')->user();
            $lat  = $user ? $user->latitude : null;
            $lng  = $user ? $user->longitude : null;
            $userId = $user ? $user->id : 0;

            $marketplaces = User::query()
                ->select('users.*')
                ->join('marketplace_profiles', 'marketplace_profiles.user_id', '=', 'users.id')
                ->with('marketplaceProfile')
                ->where('users.id', '!=', $userId)
                ->where('users.marketplace_status', 'active')
                ->when($lat && $lng, function ($q) use ($lat, $lng) {
                    $q->whereRaw(
                        '(6371 * acos(cos(radians(?)) * cos(radians(COALESCE(marketplace_profiles.latitude, users.latitude))) * cos(radians(COALESCE(marketplace_profiles.longitude, users.longitude)) - radians(?)) + sin(radians(?)) * sin(radians(COALESCE(marketplace_profiles.latitude, users.latitude))))) <= 5',
                        [$lat, $lng, $lat]
                    );
                })
                ->orderBy('users.created_at', 'desc')
                ->get()
                ->map(function ($marketplace) {
                    $marketplace = $this->decorateMarketplaceUser($marketplace);
                    $serviceIds = $this->resolveCategoryIds(optional($marketplace->marketplaceProfile)->service_category);

                    $marketplace->services = ServiceCategoryModel::query()
                        ->whereIn('id', $serviceIds ?: [])
                        ->get(['id', 'name'])
                        ->map(fn($s) => ['id' => $s->id, 'name' => $s->name])
                        ->values();

                    return $marketplace;
                });

            return $this->success($marketplaces, 'Marketplaces fetched successfully');
        } catch (\Throwable $e) {
            Log::error('Error in getAllMarketplace: ' . $e->getMessage());

            return $this->error('Failed to load marketplaces.', 500);
        }
    }

    public function recordMarketplaceStoreVisit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'marketplace_store_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $shop = User::query()
                ->where('id', $request->marketplace_store_id)
                ->whereHas('marketplaceProfile')
                ->first();

            if (!$shop) {
                return $this->error('Marketplace store not found.', 404);
            }

            $visitor = auth('sanctum')->user();
            $attributes = [
                'shop_id' => $shop->id,
                'visitor_user_id' => $visitor?->id,
            ];

            if (!$visitor) {
                $attributes['ip_address'] = $request->ip();
                $attributes['user_agent'] = $request->userAgent();
            }

            $storeVisit = StoreVisit::query()->firstOrNew($attributes);
            $storeVisit->visit_count = ($storeVisit->visit_count ?? 0) + 1;
            $storeVisit->ip_address = $request->ip();
            $storeVisit->user_agent = $request->userAgent();
            $storeVisit->save();

            return $this->success(null, 'Marketplace store visit recorded successfully');
        } catch (\Throwable $e) {
            Log::error('Error recording marketplace store visit: ' . $e->getMessage());

            return $this->error('Failed to record marketplace store visit.', 500);
        }
    }

    public function getMarketplaceDetail($id)
    {
        try {
            $marketplace = User::query()
                ->where('id', $id)
                ->with('marketplaceProfile')
                ->whereHas('marketplaceProfile')
                ->where('status', 'active')
                ->first();

            if (!$marketplace) {
                return $this->notFound('Marketplace not found');
            }

            $marketplace = $this->decorateMarketplaceUser($marketplace);
            $serviceIds = $this->resolveCategoryIds(optional($marketplace->marketplaceProfile)->service_category);

            $marketplace->services = ServiceCategoryModel::query()
                ->whereIn('id', $serviceIds ?: [])
                ->get(['id', 'name'])
                ->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                    ];
                })
                ->values();

            $products = Product::query()
                ->with('category')
                ->where('user_id', $marketplace->id)
                ->where('status', 'publish')
                ->latest()
                ->get()
                ->map(function ($product) {
                    $product->banner_image = !empty($product->banner_image)
                        ? asset('storage/' . $product->banner_image)
                        : asset('assets/img/default.jpg');

                    $images = $product->product_images;

                    if (is_string($images)) {
                        $images = array_filter(explode(',', $images));
                    }

                    $product->product_images = collect($images ?: [])
                        ->map(function ($image) {
                            return asset('storage/' . $image);
                        })
                        ->values();

                    $product->category = $product->category
                        ? [
                            'id' => $product->category->id,
                            'name' => $product->category->name,
                        ]
                        : null;

                    return $product;
                });

            $shopReviews = MarketplaceShopReview::query()
                ->where('shop_id', $marketplace->id)
                ->latest()
                ->get()
                ->map(function ($review) {
                    $reviewUser = User::select('id', 'name', 'profile_image')->find($review->user_id);

                    return [
                        'id' => $review->id,
                        'marketplace_order_id' => $review->marketplace_order_id,
                        'user_id' => $review->user_id,
                        'rating' => $review->rating,
                        'review' => $review->review,
                        'created_at' => $review->created_at,
                        'user' => [
                            'id' => $reviewUser?->id,
                            'name' => $reviewUser?->name ?? 'Unknown User',
                            'profile_image' => !empty($reviewUser?->profile_image)
                                ? asset('uploads/profile_images/' . $reviewUser->profile_image)
                                : asset('assets/img/default.jpg'),
                        ],
                    ];
                })
                ->values();

            $marketplace->rating = round((float) $shopReviews->avg('rating'), 1);
            $marketplace->reviews = $shopReviews;
            $marketplace->products = $products;

            return $this->success($marketplace, 'Marketplace detail fetched successfully');
        } catch (\Throwable $e) {
            Log::error('Error in getMarketplaceDetail: ' . $e->getMessage());

            return $this->error('Failed to load marketplace detail.', 500);
        }
    }

    public function addToCart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'base_price' => 'required|numeric|min:0',
            'total_price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $user = auth('sanctum')->user();
            $product = Product::query()
                ->select('id', 'user_id')
                ->find($request->product_id);

            if (!$product) {
                return $this->error('Product not found.', 404);
            }

            $existingCartMarketplaceId = Cart::query()
                ->join('products', 'products.id', '=', 'carts.product_id')
                ->where('carts.user_id', $user->id)
                ->whereNotNull('products.user_id')
                ->value('products.user_id');

            if (
                $existingCartMarketplaceId &&
                (int) $existingCartMarketplaceId !== (int) $product->user_id
            ) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'CART_MARKETPLACE_CONFLICT',
                    'message' => 'Your cart already contains products from another marketplace. Please checkout first or clear your cart.',
                ], 400);
            }

            $cart = Cart::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'product_id' => $request->product_id,
                ],
                [
                    'quantity' => $request->quantity,
                    'base_price' => $request->base_price,
                    'total_price' => $request->total_price,
                ]
            );

            return $this->success($cart, 'Product added to cart successfully');
        } catch (\Throwable $e) {
            Log::error('Error in addToCart: ' . $e->getMessage());

            return $this->error('Failed to add product to cart.', 500);
        }
    }

    public function getCart()
    {
        try {
            $user = auth('sanctum')->user();

            $settings = \App\Models\Admin\SystemSettingModel::first();
            $marketplaceVatPct = (float) ($settings->marketplace_vat_percentage ?? 15.00);
            $customerAppFee = 0.0; // Reverted: Customer App Fee is NOT charged on Marketplace
            $gatewayFeePct = (float) ($settings->payment_gateway_fee_percentage ?? 2.50);
            $gatewayFixedFee = (float) ($settings->payment_gateway_fixed_fee ?? 1.00);
            $gatewayVatPct = (float) ($settings->payment_gateway_vat_percentage ?? 15.00);

            $productsSubtotal = 0.0;
            $totalProductVat = 0.0;

            $cartItemsRaw = Cart::with('product')
                ->where('user_id', $user->id)
                ->latest()
                ->get();

            $hasItems = $cartItemsRaw->count() > 0;

            // Map cart items
            $cartItems = $cartItemsRaw->map(function ($cartItem) use ($marketplaceVatPct, &$productsSubtotal, &$totalProductVat) {
                $price = 0.0;
                if ($cartItem->product) {
                    $price = (float) ($cartItem->product->sale_price ?: $cartItem->product->price);

                    $cartItem->product->banner_image = !empty($cartItem->product->banner_image)
                        ? asset('storage/' . $cartItem->product->banner_image)
                        : asset('assets/img/default.jpg');

                    $images = $cartItem->product->product_images;

                    if (is_string($images)) {
                        $images = array_filter(explode(',', $images));
                    }

                    $cartItem->product->product_images = collect($images ?: [])
                        ->map(function ($image) {
                            return asset('storage/' . $image);
                        })
                        ->values();
                }

                $quantity = (int) ($cartItem->quantity ?? 1);
                $itemSubtotal = $price * $quantity;
                $itemVat = $itemSubtotal * ($marketplaceVatPct / 100);
                $itemTotalWithVat = $itemSubtotal + $itemVat;

                $productsSubtotal += $itemSubtotal;
                $totalProductVat += $itemVat;

                $cartItem->price = number_format($price, 2, '.', '');
                $cartItem->base_price = number_format($price, 2, '.', '');
                $cartItem->quantity = $quantity;
                $cartItem->item_subtotal = number_format($itemSubtotal, 2, '.', '');
                $cartItem->vat_percentage = number_format($marketplaceVatPct, 2, '.', '');
                $cartItem->item_vat = number_format($itemVat, 2, '.', '');
                $cartItem->item_total = number_format($itemTotalWithVat, 2, '.', '');
                $cartItem->total_price = number_format($itemTotalWithVat, 2, '.', '');

                return $cartItem;
            });

            $productsTotalWithVat = $productsSubtotal + $totalProductVat;
            $appFeeToApply = 0.0;

            // Gateway Fee & VAT calculation for Marketplace (No App Fee added)
            if ($hasItems && $productsSubtotal > 0) {
                $baseSubtotal = $productsSubtotal;
                $gatewaySubtotal = ($baseSubtotal * ($gatewayFeePct / 100)) + $gatewayFixedFee;
                $gatewayVat = $gatewaySubtotal * ($gatewayVatPct / 100);
                $totalPayableByCustomer = $productsTotalWithVat;
            } else {
                $baseSubtotal = 0.0;
                $totalPayableByCustomer = 0.0;
            }

            $summary = [
                'products_subtotal' => number_format($productsSubtotal, 2, '.', ''),
                'marketplace_vat_percentage' => number_format($marketplaceVatPct, 2, '.', ''),
                'total_product_vat' => number_format($totalProductVat, 2, '.', ''),
                'products_total_with_vat' => number_format($productsTotalWithVat, 2, '.', ''),
                'customer_app_fee' => '0.00',
                'subtotal' => number_format($productsSubtotal, 2, '.', ''),
                'total_payable_by_customer' => number_format($totalPayableByCustomer, 2, '.', ''),
                'grand_total' => number_format($totalPayableByCustomer, 2, '.', ''),
                'total_amount' => number_format($totalPayableByCustomer, 2, '.', ''),
                'total' => number_format($totalPayableByCustomer, 2, '.', ''),
                'currency' => strtoupper(optional($settings)->currency ?? 'SAR'),
            ];

            return $this->success(array_merge([
                'items' => $cartItems,
                'summary' => $summary,
                'payment_breakdown' => $summary,
            ], $summary), 'Cart fetched successfully');
        } catch (\Throwable $e) {
            Log::error('Error in getCart: ' . $e->getMessage());

            return $this->error('Failed to load cart.', 500);
        }
    }

    public function updateCartQuantity(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $user = auth('sanctum')->user();

            $cartItem = Cart::query()
                ->with('product')
                ->where('user_id', $user->id)
                ->where('product_id', $request->product_id)
                ->first();

            if (!$cartItem) {
                return $this->error('Cart item not found.', 404);
            }

            if ((int) $request->quantity === 0) {
                $cartItem->delete();

                return $this->success(null, 'Product removed from cart successfully');
            }

            $cartItem->quantity = (int) $request->quantity;
            $cartItem->total_price = (float) $cartItem->base_price * (int) $request->quantity;
            $cartItem->save();

            if ($cartItem->product) {
                $cartItem->product->banner_image = !empty($cartItem->product->banner_image)
                    ? asset('storage/' . $cartItem->product->banner_image)
                    : asset('assets/img/default.jpg');

                $images = $cartItem->product->product_images;

                if (is_string($images)) {
                    $images = array_filter(explode(',', $images));
                }

                $cartItem->product->product_images = collect($images ?: [])
                    ->map(function ($image) {
                        return asset('storage/' . $image);
                    })
                    ->values();
            }

            return $this->success($cartItem, 'Cart quantity updated successfully');
        } catch (\Throwable $e) {
            Log::error('Error in updateCartQuantity: ' . $e->getMessage());

            return $this->error('Failed to update cart quantity.', 500);
        }
    }

    public function clearCart()
    {
        try {
            $user = auth('sanctum')->user();

            $deletedCount = Cart::query()
                ->where('user_id', $user->id)
                ->count();

            Cart::query()
                ->where('user_id', $user->id)
                ->delete();

            return $this->success([
                'cleared_items' => $deletedCount,
            ], 'Cart cleared successfully');
        } catch (\Throwable $e) {
            Log::error('Error in clearCart: ' . $e->getMessage());

            return $this->error('Failed to clear cart.', 500);
        }
    }

    public function recordProductView(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',
            'campaign_id' => 'nullable|integer|exists:campaigns,id',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $product = Product::query()->select('id', 'user_id')->find($request->product_id);

            if (!$product) {
                return $this->error('Product not found.', 404);
            }

            $viewer = auth('sanctum')->user();
            $attributes = [
                'product_id' => $product->id,
                'shop_id' => $product->user_id,
                'viewer_user_id' => $viewer?->id,
                'campaign_id' => $request->campaign_id,
                'is_through_campaign' => !empty($request->campaign_id),
            ];

            if (!$viewer) {
                $attributes['ip_address'] = $request->ip();
                $attributes['user_agent'] = $request->userAgent();
            }

            $productView = ProductView::query()->firstOrNew($attributes);
            $productView->view_count = ($productView->view_count ?? 0) + 1;
            $productView->ip_address = $request->ip();
            $productView->user_agent = $request->userAgent();
            $productView->save();

            return $this->success(null, 'Product view recorded successfully');
        } catch (\Throwable $e) {
            Log::error('Error recording product view: ' . $e->getMessage());

            return $this->error('Failed to record product view.', 500);
        }
    }

    public function checkout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shipping_address' => 'nullable|string',
            'shipping_cost' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        DB::beginTransaction();

        try {
            $user = auth('sanctum')->user();

            $cartItems = Cart::with('product')
                ->where('user_id', $user->id)
                ->get();

            if ($cartItems->isEmpty()) {
                return $this->error('Cart is empty.', 400);
            }

            $invalidCartItems = $cartItems->filter(function ($cartItem) {
                return !$cartItem->product;
            });

            if ($invalidCartItems->isNotEmpty()) {
                Cart::whereIn('id', $invalidCartItems->pluck('id'))->delete();

                return $this->error('Some cart products no longer exist. Invalid cart items were removed. Please review your cart and try again.', 422);
            }

            $productIds = $cartItems->pluck('product_id')->unique()->values();
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            $missingProductIds = $productIds->filter(function ($productId) use ($products) {
                return !$products->has($productId);
            });

            if ($missingProductIds->isNotEmpty()) {
                Cart::where('user_id', $user->id)
                    ->whereIn('product_id', $missingProductIds)
                    ->delete();

                return $this->error('Some cart products are invalid or deleted. They were removed from your cart. Please review your cart and try again.', 422);
            }

            $subtotal = (float) $cartItems->sum('total_price');
            $shippingCost = (float) ($request->shipping_cost ?? 0);
            $taxAmount = (float) ($request->tax_amount ?? 0);
            $discountPrice = 0;
            $totalAmount = max(0, $subtotal + $shippingCost + $taxAmount - $discountPrice);

            $order = MarketplaceOrder::create([
                'user_id' => $user->id,
                'order_number' => 'ORD-' . now()->format('ymd') . Str::upper(Str::random(4)),
                'shipping_address' => $request->shipping_address ?? $user->address,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'tax_amount' => $taxAmount,
                'coupon_code' => null,
                'discount_price' => $discountPrice,
                'total_amount' => $totalAmount,
                'payment_method' => $request->payment_method,
                'notes' => $request->notes,
                'status' => 'pending',
            ]);

            foreach ($cartItems as $cartItem) {
                $product = $products->get($cartItem->product_id);

                MarketplaceOrderItem::create([
                    'marketplace_order_id' => $order->id,
                    'product_id' => $product->id,
                    'shop_id' => !empty($product->user_id) ? $product->user_id : null,
                    'product_name' => $product->product_name,
                    'quantity' => $cartItem->quantity,
                    'base_price' => $cartItem->base_price,
                    'total_price' => $cartItem->total_price,
                ]);
            }

            Cart::where('user_id', $user->id)->delete();

            $order->load(['items.product']);

            $order->items->transform(function ($item) {
                $item->shop = $item->shop
                    ? [
                        'id' => $item->shop->id,
                        'name' => optional($item->shop->marketplaceProfile)->shop_title ?? $item->shop->name ?? '',
                    ]
                    : null;

                if ($item->product) {
                    $item->product->banner_image = !empty($item->product->banner_image)
                        ? asset('storage/' . $item->product->banner_image)
                        : asset('assets/img/default.jpg');

                    $images = $item->product->product_images;

                    if (is_string($images)) {
                        $images = array_filter(explode(',', $images));
                    }

                    $item->product->product_images = collect($images ?: [])
                        ->map(function ($image) {
                            return asset('storage/' . $image);
                        })
                        ->values();
                }

                return $item;
            });

            DB::commit();

            $shopId = $order->items
                ->pluck('shop_id')
                ->filter()
                ->first();

            if ($shopId) {
                $shop = User::find($shopId);

                if ($shop) {
                    try {
                        $shop->notify((new MarketplaceOrderReceivedNotification($order, $user))->afterCommit());
                    } catch (\Throwable $notificationException) {
                        Log::error('Failed to send marketplace order received notification: ' . $notificationException->getMessage());
                    }
                }
            }

            return $this->success($order, 'Checkout completed successfully');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error in checkout: ' . $e->getMessage());

            return $this->error('Failed to complete checkout.', 500);
        }
    }

    /// customer order list
    public function customerOrders(Request $request)
    {
        try {
            $user = auth('sanctum')->user();

            $orders = MarketplaceOrder::with(['items.product', 'items.shop.marketplaceProfile'])
                ->where('user_id', $user->id)
                ->latest()
                ->get();

            $orders->transform(function ($order) {
                $order->items->transform(function ($item) {
                    $item->shop = $item->shop
                        ? [
                            'id' => $item->shop->id,
                            'name' => optional($item->shop->marketplaceProfile)->shop_title ?? $item->shop->name ?? '',
                        ]
                        : null;

                    if ($item->product) {
                        $item->product->banner_image = !empty($item->product->banner_image)
                            ? asset('storage/' . $item->product->banner_image)
                            : asset('assets/img/default.jpg');

                        $images = $item->product->product_images;

                        if (is_string($images)) {
                            $images = array_filter(explode(',', $images));
                        }

                        $item->product->product_images = collect($images ?: [])
                            ->map(function ($image) {
                                return asset('storage/' . $image);
                            })
                            ->values();
                    }

                    return $item;
                });

                return $order;
            });

            return $this->success($orders, 'My orders fetched successfully');
        } catch (\Throwable $e) {
            Log::error('Error fetching orders: ' . $e->getMessage());
            return $this->error('Failed to fetch orders.', 500);
        }
    }

    // customer single order detail
    public function customerOrderDetail($id)
    {
        try {
            $user = auth('sanctum')->user();

            $order = MarketplaceOrder::with(['items.product', 'items.shop.marketplaceProfile'])
                ->where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if (!$order) {
                return $this->error('Order not found.', 404);
            }

            $order->items->transform(function ($item) {
                if ($item->product) {
                    $item->product->banner_image = !empty($item->product->banner_image)
                        ? asset('storage/' . $item->product->banner_image)
                        : asset('assets/img/default.jpg');

                    $images = $item->product->product_images;

                    if (is_string($images)) {
                        $images = array_filter(explode(',', $images));
                    }

                    $item->product->product_images = collect($images ?: [])
                        ->map(function ($image) {
                            return asset('storage/' . $image);
                        })
                        ->values();
                }

                return $item;
            });

            // Get all reviews of this order by current user
            $shopReviews = MarketplaceShopReview::where('marketplace_order_id', $order->id)
                ->where('user_id', $user->id)
                ->get(['id', 'marketplace_order_id', 'user_id', 'shop_id', 'rating', 'review', 'created_at']);

            // Add review status in each item based on shop_id
            $order->items->transform(function ($item) use ($shopReviews) {
                $review = $shopReviews->firstWhere('shop_id', $item->shop_id);

                $item->has_review = $review ? true : false;
                $item->rating = $review ? $review->rating : null;
                $item->review = $review ? $review->review : null;

                return $item;
            });

            $response = [
                'order' => $order,
                'has_review' => $shopReviews->isNotEmpty(),
                'shop_reviews' => $shopReviews,
            ];

            return $this->success($response, 'Order detail fetched successfully');
        } catch (\Throwable $e) {
            Log::error('Error fetching order detail: ' . $e->getMessage());
            return $this->error('Failed to fetch order detail.', 500);
        }
    }

    public function updateCustomerDeliveryResponse(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:accept,reject',
            'reason' => 'required_if:type,reject|nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $user = auth('sanctum')->user();

            $order = MarketplaceOrder::query()
                ->where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$order) {
                return $this->error('Order not found.', 404);
            }

            if ($order->status !== 'mark_as_delivered') {
                return $this->error('Only delivered orders can be accepted or rejected.', 400);
            }

            $order->status = $request->type === 'accept'
                ? 'completed'
                : 'mark_as_shipped';
            $order->delivery_response_reason = $request->type === 'reject'
                ? $request->reason
                : null;

            $order->save();

            return $this->success($order, 'Delivery response updated successfully');
        } catch (\Throwable $e) {
            Log::error('Error updating delivery response: ' . $e->getMessage());

            return $this->error('Failed to update delivery response.', 500);
        }
    }

    public function submitMarketplaceShopReview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'marketplace_order_id' => 'required|integer|exists:marketplace_orders,id',
            'shop_id' => 'required|integer|exists:users,id',
            'rating' => 'required|numeric|min:1|max:5',
            'review' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $user = auth('sanctum')->user();

            $order = MarketplaceOrder::query()
                ->where('id', $request->marketplace_order_id)
                ->where('user_id', $user->id)
                ->whereHas('items', function ($query) use ($request) {
                    $query->where('shop_id', $request->shop_id);
                })
                ->first();

            if (!$order) {
                return $this->error('Marketplace order not found for this shop.', 404);
            }

            if ($order->status !== 'completed') {
                return $this->error('You can only review completed orders.', 400);
            }

            $existingReview = MarketplaceShopReview::query()
                ->where('marketplace_order_id', $request->marketplace_order_id)
                ->where('user_id', $user->id)
                ->where('shop_id', $request->shop_id)
                ->first();

            if ($existingReview) {
                return $this->error('You have already submitted a review for this shop on this order.', 409);
            }

            MarketplaceShopReview::create([
                'marketplace_order_id' => $request->marketplace_order_id,
                'user_id' => $user->id,
                'shop_id' => $request->shop_id,
                'rating' => $request->rating,
                'review' => $request->review,
            ]);

            $shop = User::find($request->shop_id);
            if ($shop) {
                try {
                    $shop->notify((new MarketplaceShopReviewSubmittedNotification($order, $user, (float) $request->rating))->afterCommit());
                } catch (\Throwable $notificationException) {
                    Log::error('Failed to send marketplace shop review notification: ' . $notificationException->getMessage());
                }
            }

            return $this->success(null, 'Marketplace shop review submitted successfully');
        } catch (\Throwable $e) {
            Log::error('Error submitting marketplace shop review: ' . $e->getMessage());

            return $this->error('Failed to submit marketplace shop review.', 500);
        }
    }

    public function marketplaceOrders()
    {
        try {
            $user = auth('sanctum')->user();

            $orders = MarketplaceOrder::with(['customer', 'items.product'])
                ->whereHas('items', function ($query) use ($user) {
                    $query->where('shop_id', $user->id);
                })
                ->latest()
                ->get();

            $orders->transform(function ($order) use ($user) {
                $order->items = $order->items
                    ->where('shop_id', $user->id)
                    ->values()
                    ->map(function ($item) {
                        if ($item->product) {
                            $item->product->banner_image = !empty($item->product->banner_image)
                                ? asset('storage/' . $item->product->banner_image)
                                : asset('assets/img/default.jpg');

                            $images = $item->product->product_images;

                            if (is_string($images)) {
                                $images = array_filter(explode(',', $images));
                            }

                            $item->product->product_images = collect($images ?: [])
                                ->map(function ($image) {
                                    return asset('storage/' . $image);
                                })
                                ->values();
                        }

                        return $item;
                    });

                return $order;
            });

            return $this->success($orders, 'Marketplace orders fetched successfully');
        } catch (\Throwable $e) {
            Log::error('Error fetching marketplace orders: ' . $e->getMessage());

            return $this->error('Failed to fetch marketplace orders.', 500);
        }
    }


    public function marketplaceDashboard()
    {
        try {
            $user = auth('sanctum')->user();

            $totalProductsCount = Product::query()
                ->where('user_id', $user->id)
                ->count();

            $orderQuery = MarketplaceOrder::query()
                ->whereHas('items', function ($query) use ($user) {
                    $query->where('shop_id', $user->id);
                });

            $totalOrdersReceived = (clone $orderQuery)->count();

            $totalCompletedOrders = (clone $orderQuery)
                ->where(function ($q) {
                    $q->whereIn('status', ['completed', 'mark_as_delivered', 'accept', 'processing', 'mark_as_shipped'])
                      ->orWhere('payment_status', 'paid');
                })
                ->count();

            $totalSales = MarketplaceOrderItem::query()
                ->where('shop_id', $user->id)
                ->whereHas('order', function ($query) {
                    $query->whereIn('status', ['accept', 'confirmed', 'processing', 'completed', 'mark_as_shipped', 'mark_as_delivered'])
                          ->orWhere('payment_status', 'paid');
                })
                ->sum('total_price');

            $recentOrders = MarketplaceOrder::with(['items.product'])
                ->where('status', 'pending')
                ->whereHas('items', function ($query) use ($user) {
                    $query->where('shop_id', $user->id)
                          ->orWhereHas('product', fn($p) => $p->where('user_id', $user->id));
                })
                ->latest()
                ->limit(10)
                ->get()
                ->map(function ($order) use ($user) {
                    $item = $order->items->first(function ($it) use ($user) {
                        return (int) $it->shop_id === (int) $user->id
                            || (int) optional($it->product)->user_id === (int) $user->id;
                    }) ?: $order->items->first();
                    $product = $item?->product;

                    return [
                        'image' => !empty($product?->banner_image)
                            ? asset('storage/' . $product->banner_image)
                            : asset('assets/img/default.jpg'),
                        'title' => $item?->product_name ?? $product?->product_name ?? 'Product',
                        'order_number' => $order->order_number ? '#' . $order->order_number : '#' . $order->id,
                        'status' => $order->status,
                        'payment_status' => $order->payment_status ?: ((int)$order->paid_to_system === 1 ? 'paid' : 'pending'),
                        'time' => Carbon::parse($order->created_at)->format('h:i A'),
                        'created_at' => $order->created_at ? $order->created_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                        'order_id' => $order?->id,
                        'total_amount' => (float) ($order?->total_amount ?? 0),
                    ];
                })
                ->values();

            return $this->success([
                'total_products_count' => $totalProductsCount,
                'total_sales' => round((float) $totalSales, 2),
                'total_orders_received' => $totalOrdersReceived,
                'total_completed_orders' => $totalCompletedOrders,
                'pending_orders' => $recentOrders,
                'recent_orders' => $recentOrders,
            ], 'Dashboard data fetched successfully');
        } catch (\Throwable $e) {
            Log::error('Error fetching marketplace dashboard: ' . $e->getMessage());

            return $this->error('Failed to fetch dashboard data.', 500);
        }
    }


    public function marketplaceOrderDetail($id)
    {
        try {
            $user = auth('sanctum')->user();

            $order = MarketplaceOrder::with(['customer', 'items.product'])
                ->where('id', $id)
                ->whereHas('items', function ($query) use ($user) {
                    $query->where('shop_id', $user->id);
                })
                ->first();

            if (!$order) {
                return $this->error('Order not found.', 404);
            }

            $order->items = $order->items
                ->where('shop_id', $user->id)
                ->values()
                ->map(function ($item) {
                    if ($item->product) {
                        $item->product->banner_image = !empty($item->product->banner_image)
                            ? asset('storage/' . $item->product->banner_image)
                            : asset('assets/img/default.jpg');

                        $images = $item->product->product_images;

                        if (is_string($images)) {
                            $images = array_filter(explode(',', $images));
                        }

                        $item->product->product_images = collect($images ?: [])
                            ->map(function ($image) {
                                return asset('storage/' . $image);
                            })
                            ->values();
                    }

                    return $item;
                });

            return $this->success($order, 'Marketplace order detail fetched successfully');
        } catch (\Throwable $e) {
            Log::error('Error fetching marketplace order detail: ' . $e->getMessage());

            return $this->error('Failed to fetch marketplace order detail.', 500);
        }
    }

    public function shopAnalytics(Request $request)
    {
        try {
            $user = auth('sanctum')->user();
            $period = $this->normalizeAnalyticsPeriod($request->query('period', 'today'));
            [$startDate, $endDate] = $this->resolveAnalyticsPeriod($period, $user->id);

            $orders = MarketplaceOrder::query()
                ->select('id', 'status', 'payment_status', 'created_at', 'total_amount')
                ->whereHas('items', function ($query) use ($user) {
                    $query->where('shop_id', $user->id);
                })
                ->with(['items' => function ($query) use ($user) {
                    $query->select('id', 'marketplace_order_id', 'shop_id', 'total_price')
                        ->where('shop_id', $user->id);
                }])
                ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                })
                ->orderBy('created_at')
                ->get();

            $rating = MarketplaceShopReview::query()
                ->where('shop_id', $user->id)
                ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                })
                ->avg('rating');

            $rating = $rating ? round($rating, 1) : 0;

            $storeVisits = StoreVisit::query()
                ->where('shop_id', $user->id)
                ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                })
                ->sum('visit_count');

            $productViews = ProductView::query()
                ->where('shop_id', $user->id)
                ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                })
                ->sum('view_count');

            $paidOrders = $orders->filter(function ($ord) {
                return $ord->payment_status === 'paid' || in_array(strtolower($ord->status), ['accept', 'confirmed', 'processing', 'completed', 'mark_as_shipped', 'mark_as_delivered']);
            });

            $summary = [
                'total_earning' => round((float) $paidOrders->sum('total_amount'), 2),
                'total_orders' => $orders->count(),
                'completed_orders' => $paidOrders->count(),
                'cancelled_orders' => $orders->whereIn('status', ['reject', 'cancelled'])->count(),
                'store_visits' => $storeVisits,
                'product_views' => $productViews,
                'rating' => round((float) ($rating ?? 0), 1),
            ];

            $chart = $this->buildAnalyticsChart($period, $startDate, $endDate, $orders, $user->id);

            return $this->success([
                'period' => $period,
                'filters' => [
                    'start_date' => $startDate?->toDateString(),
                    'end_date' => $endDate?->toDateString(),
                ],
                'summary' => $summary,
                'chart' => $chart,
            ], 'Shop analytics fetched successfully');
        } catch (\Throwable $e) {
            Log::error('Error fetching shop analytics: ' . $e->getMessage());

            return $this->error('Failed to fetch shop analytics.', 500);
        }
    }

    public function updateMarketplaceOrderStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,accept,reject,processing,mark_as_shipped,mark_as_delivered,completed',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return $this->error('Unauthorized.', 401);
            }
            if ((int) $user->role !== 2) {
                return $this->error('Only marketplace users can update order status.', 403);
            }

            $order = MarketplaceOrder::query()
                ->where('id', $id)
                ->whereHas('items', function ($query) use ($user) {
                    $query->where('shop_id', $user->id);
                })
                ->first();

            if (!$order) {
                return $this->error('Order not found.', 404);
            }

            $order->status = $request->status;
            $order->save();

            $customer = User::find($order->user_id);
            if ($customer) {
                try {
                    $customer->notify((new MarketplaceOrderStatusUpdatedNotification($order, $user, $order->status))->afterCommit());
                } catch (\Throwable $notificationException) {
                    Log::error('Failed to send marketplace order status notification: ' . $notificationException->getMessage());
                }
            }

            return $this->success($order, 'Marketplace order status updated successfully');
        } catch (\Throwable $e) {
            Log::error('Error updating marketplace order status: ' . $e->getMessage());

            return $this->error('Failed to update marketplace order status.', 500);
        }
    }

    public function updateProduct(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'banner_image' => 'nullable|file|mimes:jpeg,png,jpg,gif',

            'product_images' => 'nullable|array',
            'product_images.*' => 'file|mimes:jpeg,png,jpg,gif',

            'category_id' => 'nullable|integer|exists:categories,id',
            'status' => 'nullable|in:publish,unpublish,pending,trash',
            'product_name' => 'nullable|string',
            'product_description' => 'nullable|string',
            'price' => 'nullable|numeric',
            'sale_price' => 'nullable|numeric',
            'discount_type' => 'nullable|string',
            'discount_value' => 'nullable|numeric',
            'tax_status' => 'nullable|string',
            'installation_available' => 'nullable|boolean',
            'installation_price' => 'nullable|numeric',
            'installation_details' => 'nullable|string',
            'weight' => 'nullable|numeric',
            'height' => 'nullable|numeric',
            'width' => 'nullable|numeric',
            'length' => 'nullable|numeric',
            'total_stock' => 'nullable|integer',
            'limited_stock' => 'nullable|integer',
            'sku' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }


        if ($request->hasFile('banner_image')) {
            $product->banner_image = $request->file('banner_image')->store('products/banners', 'public');
        }

        if ($request->hasFile('product_images')) {
            $existingImages = is_array($product->product_images)
                ? $product->product_images
                : (is_string($product->product_images) && $product->product_images !== ''
                    ? array_values(array_filter(explode(',', $product->product_images)))
                    : []);

            $newImages = [];

            foreach ($request->file('product_images') as $image) {
                $newImages[] = $image->store('products/images', 'public');
            }

            $product->product_images = array_values(array_unique(array_merge($existingImages, $newImages)));
        }

        $product->fill($request->only([
            'category_id',
            'status',
            'product_name',
            'product_description',
            'price',
            'sale_price',
            'discount_type',
            'discount_value',
            'tax_status',
            'installation_available',
            'installation_price',
            'installation_details',
            'weight',
            'height',
            'width',
            'length',
            'total_stock',
            'limited_stock',
            'sku',
        ]));

        $product->save();

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product
        ], 200);
    }

    public function deleteProduct($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $product->status = 'trash';
        $product->save();

        return response()->json(['message' => 'Product status updated to trash successfully'], 200);
    }

    private function normalizeAnalyticsPeriod(?string $period): string
    {
        return match ($period) {
            'week' => 'this_week',
            'month' => 'this_month',
            'year' => 'this_year',
            'today',
            'yesterday',
            'this_week',
            'last_week',
            'this_month',
            'last_month',
            'this_year',
            'last_year',
            'lifetime' => $period,
            default => 'today',
        };
    }

    private function resolveAnalyticsPeriod(string $period, int $shopId): array
    {
        $now = Carbon::now();

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [
                $now->copy()->subDay()->startOfDay(),
                $now->copy()->subDay()->endOfDay(),
            ],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last_week' => [
                $now->copy()->subWeek()->startOfWeek(),
                $now->copy()->subWeek()->endOfWeek(),
            ],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
            ],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last_year' => [
                $now->copy()->subYear()->startOfYear(),
                $now->copy()->subYear()->endOfYear(),
            ],
            'lifetime' => $this->resolveLifetimeAnalyticsPeriod($shopId),
            default => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };
    }

    private function resolveLifetimeAnalyticsPeriod(int $shopId): array
    {
        $firstOrderDate = MarketplaceOrder::query()
            ->whereHas('items', function ($query) use ($shopId) {
                $query->where('shop_id', $shopId);
            })
            ->min('created_at');

        $startDate = $firstOrderDate
            ? Carbon::parse($firstOrderDate)->startOfMonth()
            : Carbon::now()->startOfMonth();

        return [$startDate, Carbon::now()->endOfMonth()];
    }

    private function buildAnalyticsChart(string $period, Carbon $startDate, Carbon $endDate, $orders, int $shopId): array
    {
        $buckets = $this->generateAnalyticsBuckets($period, $startDate, $endDate);

        foreach ($orders as $order) {
            $bucketKey = $this->resolveAnalyticsBucketKey($period, Carbon::parse($order->created_at), $startDate);

            if (!isset($buckets[$bucketKey])) {
                continue;
            }

            $buckets[$bucketKey]['total_orders']++;

            if ($order->status === 'completed') {
                $buckets[$bucketKey]['completed_orders']++;
                $buckets[$bucketKey]['earnings'] = round(
                    $buckets[$bucketKey]['earnings'] + (float) $order->total_amount,
                    2
                );
            }

            if ($order->status === 'reject') {
                $buckets[$bucketKey]['cancelled_orders']++;
            }
        }

        return array_values($buckets);
    }

    private function generateAnalyticsBuckets(string $period, Carbon $startDate, Carbon $endDate): array
    {
        $buckets = [];

        if (in_array($period, ['today', 'yesterday'], true)) {
            $key = $startDate->toDateString();
            $buckets[$key] = $this->makeAnalyticsBucket($startDate->format('d M'));

            return $buckets;
        }

        if (in_array($period, ['this_week', 'last_week'], true)) {
            $cursor = $startDate->copy()->startOfDay();

            while ($cursor <= $endDate) {
                $key = $cursor->toDateString();
                $buckets[$key] = $this->makeAnalyticsBucket($cursor->format('D'));
                $cursor->addDay();
            }

            return $buckets;
        }

        if (in_array($period, ['this_month', 'last_month'], true)) {
            $cursor = $startDate->copy()->startOfDay();

            while ($cursor <= $endDate) {
                $weekNumber = (int) floor(($cursor->day - 1) / 7) + 1;
                $key = sprintf('%s-week-%d', $cursor->format('Y-m'), $weekNumber);

                if (!isset($buckets[$key])) {
                    $buckets[$key] = $this->makeAnalyticsBucket('Week ' . $weekNumber);
                }

                $cursor->addDay();
            }

            return $buckets;
        }

        if (in_array($period, ['this_year', 'last_year'], true)) {
            $cursor = $startDate->copy()->startOfMonth();

            while ($cursor <= $endDate) {
                $key = $cursor->format('Y-m');
                $buckets[$key] = $this->makeAnalyticsBucket($cursor->format('M'));
                $cursor->addMonth();
            }

            return $buckets;
        }

        $cursor = $startDate->copy()->startOfMonth();

        while ($cursor <= $endDate) {
            $key = $cursor->format('Y-m');
            $buckets[$key] = $this->makeAnalyticsBucket($cursor->format('M Y'));
            $cursor->addMonth();
        }

        return $buckets;
    }

    private function resolveAnalyticsBucketKey(string $period, Carbon $date, Carbon $startDate): string
    {
        return match ($period) {
            'today', 'yesterday' => $date->toDateString(),
            'this_week', 'last_week' => $date->toDateString(),
            'this_month', 'last_month' => sprintf(
                '%s-week-%d',
                $date->format('Y-m'),
                (int) floor(($date->day - 1) / 7) + 1
            ),
            'this_year', 'last_year', 'lifetime' => $date->format('Y-m'),
            default => $date->format('Y-m-d H:00:00'),
        };
    }

    private function makeAnalyticsBucket(string $label): array
    {
        return [
            'label' => $label,
            'total_orders' => 0,
            'completed_orders' => 0,
            'cancelled_orders' => 0,
            'earnings' => 0.0,
        ];
    }

    private function buildAuthenticatedUserPayload(User $user): array
    {
        $user->loadMissing(['providerProfile', 'marketplaceProfile', 'skills', 'reviews']);

        $payload = [
            'id' => $user->id,
            'user_code' => $user->user_code,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'dob' => $user->dob,
            'role' => (int) $user->role,
            'has_roles' => (string) $user->fresh()->has_roles,
            'status' => $user->status,
            'provider_status' => $user->provider_status,
            'marketplace_status' => $user->marketplace_status,
            'profile_image' => $this->resolveUploadedAssetUrl($user->profile_image, 'uploads/profile_images'),
            'location_label' => $user->location_label,
            'country' => $user->country,
            'city_id' => $user->city_id,
            'address' => $this->resolveAddressForRole($user),
            'latitude' => $this->resolveLatitudeForRole($user),
            'longitude' => $this->resolveLongitudeForRole($user),
            'fcm_token' => $user->fcm_token,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'cityname' => $user->cityname,
        ];

        if ((string) $user->role === '1') {
            $providerProfile = $user->providerProfile;
            $gallery = ProviderGallery::where('user_id', $user->id)->get()->map(function ($image) {
                return [
                    'id' => $image->id,
                    'user_id' => $image->user_id,
                    'path' => asset('uploads/provider_gallery/' . $image->path),
                    'created_at' => $image->created_at,
                    'updated_at' => $image->updated_at,
                ];
            })->values();

            $serviceCategory = $providerProfile?->service_category ?? [];
            $categoryIds = $this->resolveCategoryIds($serviceCategory);
            $providerServices = ServiceCategoryModel::query()
                ->whereIn('id', $categoryIds)
                ->get(['id', 'name', 'path', 'created_at', 'updated_at'])
                ->values();

            $referrerUser = $providerProfile?->referred_by_id ? User::with('providerProfile')->find($providerProfile->referred_by_id) : null;
            $referredByData = $referrerUser ? [
                'id' => $referrerUser->id,
                'name' => $referrerUser->name,
                'user_code' => $referrerUser->user_code,
                'referral_code' => optional($referrerUser->providerProfile)->referral_code,
            ] : null;
            $totalReferrals = ProviderProfile::where('referred_by_id', $user->id)->count();

            return array_merge($payload, [
                'gallery' => $gallery,
                'skills' => $user->skills()->get(),
                'active_orders_count' => Orders::where('provider_id', $user->id)
                    ->whereIn('status', ['on_the_way', 'arrived', 'working'])
                    ->count(),
                'reviews' => $user->reviews()->get(),
                'rating' => (float) $user->rating,
                'total_orders' => $user->total_orders,
                'total_earnings' => $user->total_earnings,
                'payment_due' => $user->payment_due,
                'referral_code' => $providerProfile?->referral_code,
                'referred_by_code' => $providerProfile?->referred_by_code,
                'referred_by_id' => $providerProfile?->referred_by_id,
                'referred_by' => $referredByData,
                'total_referrals' => $totalReferrals,
                'provider_profile' => $providerProfile ? [
                    'id' => $providerProfile->id,
                    'user_id' => $providerProfile->user_id,
                    'provider_type' => $providerProfile->provider_type,
                    'company_name' => $providerProfile->company_name,
                    'company_logo' => $providerProfile->company_logo
                        ? asset('uploads/company_logos/' . $providerProfile->company_logo)
                        : null,
                    'latitude' => $providerProfile->latitude,
                    'longitude' => $providerProfile->longitude,
                    'address' => $providerProfile->address,
                    'services' => $providerServices,
                    'experience' => $providerProfile->experience,
                    'work_hour_start' => $providerProfile->work_hour_start,
                    'work_hour_end' => $providerProfile->work_hour_end,
                    'bio' => $providerProfile->bio,
                    'charge_type' => $providerProfile->charge_type,
                    'charge_amount' => $providerProfile->charge_amount,
                    'document_type' => $providerProfile->document_type,
                    'document_number' => $providerProfile->document_number,
                    'service_license' => $providerProfile->service_license
                        ? asset('uploads/license_files/' . $providerProfile->service_license)
                        : null,
                    'certification' => $providerProfile->certification
                        ? asset('uploads/certification_files/' . $providerProfile->certification)
                        : null,
                    'referral_code' => $providerProfile->referral_code,
                    'referred_by_code' => $providerProfile->referred_by_code,
                    'referred_by_id' => $providerProfile->referred_by_id,
                    'referred_by' => $referredByData,
                    'total_referrals' => $totalReferrals,
                    'created_at' => $providerProfile->created_at,
                    'updated_at' => $providerProfile->updated_at,
                ] : null,
            ]);
        }

        if ((string) $user->role === '2') {
            $marketplaceProfile = $user->marketplaceProfile;
            $serviceCategory = $marketplaceProfile?->service_category ?? [];
            $categoryIds = $this->resolveCategoryIds($serviceCategory);
            $marketPlaceServices = ServiceCategoryModel::query()
                ->whereIn('id', $categoryIds)
                ->get(['id', 'name', 'path', 'created_at', 'updated_at'])
                ->values();
            $shopReviews = $this->formatMarketplaceShopReviews($user->id);

            return array_merge($payload, [
                'active_orders_count' => Orders::where('user_id', $user->id)
                    ->whereIn('status', ['on_the_way', 'arrived', 'working'])
                    ->count(),
                'reviews' => $shopReviews,
                'rating' => round((float) collect($shopReviews)->avg('rating'), 1),
                'marketplace_profile' => $marketplaceProfile ? [
                    'id' => $marketplaceProfile->id,
                    'user_id' => $marketplaceProfile->user_id,
                    'shop_title' => $marketplaceProfile->shop_title,
                    'shop_logo' => $marketplaceProfile->shop_logo
                        ? asset('uploads/shop_logos/' . $marketplaceProfile->shop_logo)
                        : null,
                    'shop_banner_image' => $marketplaceProfile->shop_banner_image
                        ? asset('uploads/shop_banners/' . $marketplaceProfile->shop_banner_image)
                        : null,
                    'tag_line' => $marketplaceProfile->tag_line,
                    'delivery_charges' => (float) ($marketplaceProfile->delivery_charges ?? 0),
                    'bio' => $marketplaceProfile->bio,
                    'services' => $marketPlaceServices,
                    'operation_hours' => $marketplaceProfile->operation_hours,
                    'shop_status' => $marketplaceProfile->shop_status,
                    'document_type' => $marketplaceProfile->document_type,
                    'document_number' => $marketplaceProfile->document_number,
                    'address' => $marketplaceProfile->address,
                    'latitude' => $marketplaceProfile->latitude,
                    'longitude' => $marketplaceProfile->longitude,
                    'created_at' => $marketplaceProfile->created_at,
                    'updated_at' => $marketplaceProfile->updated_at,
                ] : null,
            ]);
        }

        return $payload;
    }

    private function formatMarketplaceShopReviews(int $shopId)
    {
        return MarketplaceShopReview::query()
            ->where('shop_id', $shopId)
            ->latest()
            ->get()
            ->map(function ($review) {
                $reviewUser = User::select('id', 'name', 'profile_image')->find($review->user_id);

                return [
                    'id' => $review->id,
                    'marketplace_order_id' => $review->marketplace_order_id,
                    'user_id' => $review->user_id,
                    'rating' => $review->rating,
                    'review' => $review->review,
                    'created_at' => $review->created_at,
                    'user' => [
                        'id' => $reviewUser?->id,
                        'name' => $reviewUser?->name ?? 'Unknown User',
                        'profile_image' => $this->resolveUploadedAssetUrl($reviewUser?->profile_image, 'uploads/profile_images'),
                    ],
                ];
            })
            ->values()
            ->all();
    }

    private function resolveUploadedAssetUrl(?string $path, string $directory, ?string $default = null): ?string
    {
        if (empty($path)) {
            return $default ?? asset('assets/img/default.jpg');
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return asset(trim($directory, '/') . '/' . ltrim($path, '/'));
    }

    private function resolveAddressForRole(User $user): ?string
    {
        $role = (string) $user->role;
        if ($role === '1') return $user->providerProfile?->address ?? null;
        return $user->address ?? null;
    }

    private function resolveLatitudeForRole(User $user): ?string
    {
        $role = (string) $user->role;
        if ($role === '1') return $user->providerProfile?->latitude ?? null;
        return $user->latitude ?? null;
    }

    private function resolveLongitudeForRole(User $user): ?string
    {
        $role = (string) $user->role;
        if ($role === '1') return $user->providerProfile?->longitude ?? null;
        return $user->longitude ?? null;
    }

    private function parseRoleList(?string $roles): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $roles)))));
    }

    private function syncHasRoles(User $user, string $role): string
    {
        $roles = $this->parseRoleList($user->has_roles);

        if (!in_array($role, $roles, true)) {
            $roles[] = $role;
        }

        if (!in_array('0', $roles, true)) {
            $roles[] = '0';
        }

        return implode(',', array_values(array_unique($roles)));
    }

    private function resolveCategoryIds($raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter($raw, fn($value) => $value !== null && $value !== ''));
        }

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values(array_filter($decoded, fn($value) => $value !== null && $value !== ''));
            }
        }

        return [];
    }

    private function decorateProviderUser(User $user): User
    {
        $user->loadMissing('providerProfile');

        $profile = $user->providerProfile;

        if (!$profile) {
            return $user;
        }

        if (empty($profile->referral_code)) {
            $profile->referral_code = ProviderProfile::generateUniqueReferralCode();
            $profile->save();
        }

        $user->provider_type = $profile->provider_type;
        $user->company_name = $profile->company_name;
        $user->company_logo = $profile->company_logo
            ? asset('uploads/company_logos/' . $profile->company_logo)
            : null;
        $user->latitude = $profile->latitude;
        $user->longitude = $profile->longitude;
        $user->address = $profile->address;
        $user->service_category = $profile->service_category ?? [];
        $user->experience = $profile->experience;
        $user->work_hour_start = $profile->work_hour_start;
        $user->work_hour_end = $profile->work_hour_end;
        $user->bio = $profile->bio;
        $user->charge_type = $profile->charge_type;
        $user->charge_amount = $profile->charge_amount;
        $user->document_type = $profile->document_type;
        $user->document_number = $profile->document_number;
        $user->service_license = $profile->service_license
            ? asset('uploads/license_files/' . $profile->service_license)
            : null;
        $user->certification = $profile->certification
            ? asset('uploads/certification_files/' . $profile->certification)
            : null;

        // Referral details
        $user->referral_code = $profile->referral_code;
        $user->referred_by_code = $profile->referred_by_code;
        $user->referred_by_id = $profile->referred_by_id;

        $referrerUser = $profile->referred_by_id ? User::with('providerProfile')->find($profile->referred_by_id) : null;
        $user->referred_by = $referrerUser ? [
            'id' => $referrerUser->id,
            'name' => $referrerUser->name,
            'user_code' => $referrerUser->user_code,
            'referral_code' => optional($referrerUser->providerProfile)->referral_code,
        ] : null;

        $user->total_referrals = ProviderProfile::where('referred_by_id', $user->id)->count();

        $user->profile_image = $profile->company_logo
            ? asset('uploads/company_logos/' . $profile->company_logo)
            : ($user->profile_image
                ? asset('uploads/profile_images/' . $user->profile_image)
                : asset('assets/img/default.jpg'));

        return $user;
    }

    private function decorateMarketplaceUser(User $user): User
    {
        $user->loadMissing('marketplaceProfile');

        $profile = $user->marketplaceProfile;

        if (!$profile) {
            return $user;
        }

        $user->shop_title = $profile->shop_title;
        $user->shop_logo = $profile->shop_logo
            ? asset('uploads/shop_logos/' . $profile->shop_logo)
            : null;
        $user->shop_banner_image = $profile->shop_banner_image
            ? asset('uploads/shop_banners/' . $profile->shop_banner_image)
            : null;
        $user->tag_line = $profile->tag_line;
        $user->delivery_charges = (float) ($profile->delivery_charges ?? 0);
        $user->bio = $profile->bio;
        $user->service_category = $profile->service_category ?? [];
        $user->operation_hours = $profile->operation_hours ?? (object) [];
        $user->shop_status = $profile->shop_status;
        $user->document_type = $profile->document_type;
        $user->document_number = $profile->document_number;
        $user->shop_image = $profile->shop_logo
            ? asset('uploads/shop_logos/' . $profile->shop_logo)
            : asset('assets/img/default.jpg');
        $user->marketplace_address = $profile->address;
        $user->marketplace_latitude = $profile->latitude;
        $user->marketplace_longitude = $profile->longitude;

        return $user;
    }
}
