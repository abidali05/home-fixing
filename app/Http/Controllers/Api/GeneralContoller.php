<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Models\AppVersion;
use App\Models\User;
use App\Models\Orders;
use App\Models\BidModel;
use App\Models\CityModel;
use App\Models\FaqModel;
use App\Models\Cart;
use Illuminate\Http\Request;
use App\Models\OrderTracking;
use App\Models\JobRequestModel;
use App\Models\ProviderGallery;
use App\Models\JobRequestImages;
use App\Models\SupportItemModel;
use App\Notifications\BidReceivedNotification;
use App\Notifications\OrderCancelledByCustomerNotification;
use App\Notifications\OrderStatusUpdatedNotification;
use App\Notifications\ProviderRespondedToRequestNotification;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\MobileBanners;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\Admin\SystemSettingModel;
use Illuminate\Support\Facades\Validator;
use App\Models\Admin\ServiceCategoryModel;

class GeneralContoller extends Controller
{
    public function system_settings()
    {
        try {
            $data = SystemSettingModel::select('system_name', 'logo', 'currency', 'payment_method')->first();

            if (!$data) {
                return $this->notFound('System settings not found');
            }

            $data->logo = asset('uploads/system_settings/' . $data->logo);

            return $this->success($data, 'System settings fetched successfully');
        } catch (Exception $e) {
            return $this->error('An error occurred while fetching system settings', 500, [
                'exception' => $e->getMessage()
            ]);
        }
    }

    public function app_version()
    {
        try {
            $appVersion = AppVersion::query()->latest()->first();

            if (!$appVersion) {
                return $this->notFound('App version not found');
            }

            return $this->success($appVersion, 'App version fetched successfully');
        } catch (\Throwable $e) {
            Log::error('Error fetching app version: ' . $e->getMessage());

            return $this->error('Failed to fetch app version.', 500);
        }
    }

    public function user_home()
    {
        try {
            $user = auth('sanctum')->user();

            $banners = MobileBanners::select('path')->orderBy('id', 'desc')->get()->map(function ($banner) {
                $banner->path = ($banner->path && $banner->path != '' && $banner->path != null) ? asset('uploads/mobile_banners/' . $banner->path) : asset('assets/img/default.jpg');
                return $banner;
            });


            $topCategoryIds = JobRequestModel::where('status', '!=', 'cancelled')
                ->select('category_id', DB::raw('COUNT(*) as total'))
                ->groupBy('category_id')
                ->orderByDesc('total')
                ->limit(8)
                ->pluck('category_id')
                ->toArray();

            if (count($topCategoryIds) < 8) {
                $randomIds = ServiceCategoryModel::whereNotIn('id', $topCategoryIds)
                    ->inRandomOrder()
                    ->limit(8 - count($topCategoryIds))
                    ->pluck('id')
                    ->toArray();

                $topCategoryIds = array_merge($topCategoryIds, $randomIds);
            }

            $popular_services = ServiceCategoryModel::select('id', 'name', 'path')
                ->whereIn('id', $topCategoryIds)
                ->get()
                ->map(function ($service) {
                    $service->path = $service->path ? asset('uploads/service_category/' . $service->path) : asset('assets/img/default.jpg');
                    return $service;
                });


            $providers = User::with(['reviews', 'providerProfile'])
                ->whereHas('providerProfile')
                ->where('id', '!=', $user->id)
                ->where('provider_status', 'active')
                ->latest()
                ->limit(4)
                ->get()
                ->map(function ($provider) {
                    $provider = $this->decorateProvider($provider);
                    $serviceCategories = $this->getProviderServiceCategories($provider);

                    $provider->categories = ServiceCategoryModel::whereIn('id', $serviceCategories)->get()
                        ->map(function ($cat) {
                            $cat->path = $cat->path && !str_starts_with($cat->path, 'http')
                                ? asset('uploads/service_category/' . $cat->path)
                                : $cat->path;
                            return $cat;
                        })
                        ->values();

                    return $provider;
                })
                ->values();

            $marketplaces = User::query()
                ->with('marketplaceProfile')
                ->whereHas('marketplaceProfile')
                ->where('id', '!=', $user->id)
                ->where('marketplace_status', 'active')
                ->latest()
                ->limit(4)
                ->get()
                ->map(function ($marketplace) {
                    $marketplace = $this->decorateMarketplace($marketplace);
                    $serviceIds = $this->resolveCategoryIds(optional($marketplace->marketplaceProfile)->service_category);

                    $services = ServiceCategoryModel::query()
                        ->whereIn('id', $serviceIds ?: [])
                        ->get(['id', 'name'])
                        ->map(function ($service) {
                            return [
                                'id' => $service->id,
                                'name' => $service->name,
                            ];
                        })
                        ->values();

                    $marketplace->services = $services;

                    return $marketplace;
                });

            $active_orders = Orders::with(['job.category', 'provider'])
                ->where('user_id', $user->id)
                ->whereIn('status', ['on_the_way', 'arrived', 'working'])
                ->latest()
                ->limit(4)
                ->get();

            $cart_count = Cart::where('user_id', $user->id)->count();

            $data = [
                'banners' => $banners,
                'popular_services' => $popular_services,
                'providers' => $providers,
                'marketplaces' => $marketplaces,
                'active_orders' => $active_orders,
                'cart_count' => $cart_count,
            ];

            return $this->success($data, 'Home page fetched successfully');
        } catch (\Exception $e) {
            return $this->error('An error occurred while fetching home page', 500, [
                'exception' => $e->getMessage()
            ]);
        }
    }

    public function view_all_services()
    {
        try {
            $data = ServiceCategoryModel::select('id', 'name', 'path')->get()->map(function ($service) {
                $service->path = $service->path ? asset('uploads/service_category/' . $service->path) : asset('assets/img/default.jpg');
                return $service;
            });
            return $this->success($data, 'All services fetched successfully');
        } catch (\Exception $e) {
            return $this->error('An error occurred while fetching all services', 500, [
                'exception' => $e->getMessage()
            ]);
        }
    }

    public function view_all_providers()
    {
        try {
            $user = auth('sanctum')->user();

            $users = User::select('id', 'name', 'profile_image')
                ->with(['reviews', 'providerProfile'])
                ->whereHas('providerProfile')
                ->where('id', '!=', $user->id)
                ->where('provider_status', 'active')
                ->get();

            foreach ($users as $provider) {
                $provider = $this->decorateProvider($provider);

                $serviceCategories = [];

                if (is_string($provider->service_category)) {
                    $decoded = json_decode($provider->service_category, true);
                    $serviceCategories = is_array($decoded)
                        ? $decoded
                        : (is_numeric($provider->service_category)
                            ? [(int) $provider->service_category]
                            : []);
                } elseif (is_int($provider->service_category)) {
                    $serviceCategories = [$provider->service_category];
                } elseif (is_array($provider->service_category)) {
                    $serviceCategories = $provider->service_category;
                }

                $serviceCategories = $this->getProviderServiceCategories($provider);
                $provider->categories = !empty($serviceCategories)
                    ? ServiceCategoryModel::whereIn('id', $serviceCategories)->get()->map(function ($cat) {
                        $cat->path = $cat->path && !str_starts_with($cat->path, 'http')
                            ? asset('uploads/service_category/' . $cat->path)
                            : $cat->path;
                        return $cat;
                    })
                    : collect();
            }

            $grouped = collect();

            foreach ($users as $provider) {
                foreach ($provider->categories as $cat) {
                    if (!$grouped->has($cat->id)) {
                        $grouped[$cat->id] = [
                            'id' => $cat->id,
                            'key' => $cat->name,
                            'providers' => collect()
                        ];
                    }
                    $grouped[$cat->id]['providers']->push($provider);
                }
            }

            $result = $grouped->map(function ($item) {
                $item['providers'] = $item['providers']->values();
                return $item;
            })->values();

            return response()->json([
                'status' => true,
                'message' => 'All services fetched successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching all services',
                'data' => [
                    'exception' => $e->getMessage()
                ]
            ], 500);
        }
    }

    public function view_all_providers_data()
    {
        try {
            $user = auth('sanctum')->user();

            $users = User::select('id', 'name', 'profile_image')
                ->with(['reviews', 'providerProfile'])
                ->whereHas('providerProfile')
                ->where('id', '!=', $user->id)
                ->where('provider_status', 'active')
                ->get();

            $users = $users->map(function ($provider) {
                $provider->loadMissing('providerProfile');
                $providerProfile = $provider->providerProfile;

                if ($providerProfile) {
                    $serviceIds = $this->getProviderServiceCategories($provider);

                    $providerProfile->services = ServiceCategoryModel::query()
                        ->whereIn('id', $serviceIds)
                        ->get(['id', 'name', 'path', 'created_at', 'updated_at'])
                        ->map(function ($category) {
                            $category->path = $category->path && !str_starts_with($category->path, 'http')
                                ? asset('uploads/service_category/' . $category->path)
                                : $category->path;

                            return $category;
                        })
                        ->values();
                }

                return $provider;
            });

            return response()->json([
                'status' => true,
                'message' => 'All services fetched successfully',
                'data' => $users
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching all services',
                'data' => [
                    'exception' => $e->getMessage()
                ]
            ], 500);
        }
    }

    public function get_providers_by_service($id)
    {
        try {
            $user = auth('sanctum')->user();

            $data = User::with(['reviews', 'providerProfile'])
                ->whereHas('providerProfile')
                ->where('id', '!=', $user->id)
                ->where('provider_status', 'active')
                ->get();

            $data = $data
                ->filter(function ($provider) use ($id) {
                    return in_array((int) $id, array_map('intval', $this->getProviderServiceCategories($provider)), true);
                })
                ->map(function ($provider) {
                    $provider = $this->decorateProvider($provider);
                    $serviceIds = $this->getProviderServiceCategories($provider);

                    $provider->categories = ServiceCategoryModel::whereIn('id', $serviceIds)->get()
                        ->map(function ($category) {
                            $category->path = $category->path && !str_starts_with($category->path, 'http')
                                ? asset('uploads/service_category/' . $category->path)
                                : $category->path;

                            return $category;
                        })
                        ->values();

                    return $provider;
                })
                ->values();

            return $this->success($data, 'Providers fetched successfully');
        } catch (\Exception $e) {
            return $this->error('An error occurred while fetching providers', 500, [
                'exception' => $e->getMessage()
            ]);
        }
    }


    public function get_providers_details($id)
    {
        try {
            $provider = User::with(['reviews', 'skills', 'providerProfile'])
                ->whereHas('providerProfile')
                ->where('status', 'active')
                ->where('id', $id)
                ->first();
            if (!$provider) {
                return $this->notFound('Provider not found');
            }
            $provider = $this->decorateProvider($provider);
            $gallery = ProviderGallery::select('path')->where('user_id', $id)->get();
            foreach ($gallery as $image) {
                $image->path = $image->path ? asset('uploads/provider_gallery/' . $image->path) : asset('assets/img/default.jpg');
            }
            $provider->gallery = $gallery;
            return $this->success($provider, 'Providers fetched successfully');
        } catch (\Exception $e) {
            return $this->error('An error occurred while fetching providers', 500, [
                'exception' => $e->getMessage()
            ]);
        }
    }

    public function autocomplete_search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 'Validation failed.');
        }

        try {
            $services = ServiceCategoryModel::where('name', 'like', '%' . $request->name . '%')->get();

            foreach ($services as $service) {
                $providers = User::with('providerProfile')
                    ->whereHas('providerProfile')
                    ->where('status', 'active')
                    ->get()
                    ->filter(function ($provider) use ($service) {
                        return in_array((int) $service->id, array_map('intval', $this->getProviderServiceCategories($provider)), true);
                    })
                    ->count();

                $service->results = $providers;
            }

            return $this->success($services, 'Providers fetched successfully');
        } catch (\Exception $e) {
            return $this->error('An error occurred while fetching providers.', 500, [
                'exception' => $e->getMessage()
            ]);
        }
    }

    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'service_id' => 'required|exists:categories,id',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 'Validation failed.');
        }

        try {
            $providers = User::with(['reviews', 'providerProfile'])
                ->whereHas('providerProfile')
                ->where('status', 'active')
                ->get()
                ->filter(function ($provider) use ($request) {
                    return in_array((int) $request->service_id, array_map('intval', $this->getProviderServiceCategories($provider)), true);
                })
                ->values();
            foreach ($providers as $provider) {
                $provider = $this->decorateProvider($provider);
            }

            return $this->success($providers, 'Providers fetched successfully');
        } catch (\Exception $e) {
            return $this->error('An error occurred while fetching providers.', 500, [
                'exception' => $e->getMessage()
            ]);
        }
    }

    public function cities()
    {
        try {
            $cities = CityModel::all();
            return $this->success($cities, 'Cities fetched successfully');
        } catch (\Exception $e) {
            return $this->error('An error occurred while fetching cities.');
        }
    }

    public function toggle_favorite_provider(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'provider_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 'Validation failed.');
        }

        try {
            $user = auth('sanctum')->user();
            $provider = User::where('id', $request->provider_id)->whereHas('providerProfile')->first();

            if (!$provider) {
                return $this->notFound('Service provider not found');
            }

            $existing = DB::table('favorite_providers')
                ->where('user_id', $user->id)
                ->where('provider_id', $provider->id)
                ->first();

            if ($existing) {
                DB::table('favorite_providers')
                    ->where('user_id', $user->id)
                    ->where('provider_id', $provider->id)
                    ->delete();

                return $this->success([
                    'provider_id' => (int) $provider->id,
                    'is_favorite' => false,
                ], 'Provider removed from favorites');
            }

            DB::table('favorite_providers')->insert([
                'user_id' => $user->id,
                'provider_id' => $provider->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->success([
                'provider_id' => (int) $provider->id,
                'is_favorite' => true,
            ], 'Provider added to favorites');
        } catch (\Exception $e) {
            return $this->error('An error occurred while updating favorites', 500, [
                'exception' => $e->getMessage()
            ]);
        }
    }

    public function get_favorite_provider_ids()
    {
        try {
            $user = auth('sanctum')->user();

            $ids = DB::table('favorite_providers')
                ->where('user_id', $user->id)
                ->orderBy('id', 'desc')
                ->pluck('provider_id')
                ->map(function ($id) {
                    return (int) $id;
                })
                ->values();

            return $this->success($ids, 'Favorite providers fetched successfully');
        } catch (\Exception $e) {
            return $this->error('An error occurred while fetching favorite providers', 500, [
                'exception' => $e->getMessage()
            ]);
        }
    }

    public function get_favorite_provider()
    {
        try {
            $user = auth('sanctum')->user();

            $ids = DB::table('favorite_providers')
                ->where('user_id', $user->id)
                ->orderBy('id', 'desc')
                ->pluck('provider_id')
                ->map(function ($id) {
                    return (int) $id;
                })
                ->values();

            if ($ids->isEmpty()) {
                return $this->success(collect(), 'Favorite providers fetched successfully');
            }

            $providers = User::with(['reviews', 'providerProfile'])
                ->whereHas('providerProfile')
                ->where('provider_status', 'active')
                ->whereIn('id', $ids->toArray())
                ->get()
                ->map(function ($provider) {
                    $provider = $this->decorateProvider($provider);
                    $serviceIds = $this->getProviderServiceCategories($provider);

                    $provider->categories = ServiceCategoryModel::whereIn('id', $serviceIds)->get()
                        ->map(function ($category) {
                            $category->path = $category->path && !str_starts_with($category->path, 'http')
                                ? asset('uploads/service_category/' . $category->path)
                                : $category->path;
                            return $category;
                        })
                        ->values();

                    return $provider;
                })
                ->keyBy('id');

            $orderedProviders = $ids->map(function ($id) use ($providers) {
                return $providers->get($id);
            })->filter()->values();

            return $this->success($orderedProviders, 'Favorite providers fetched successfully');
        } catch (\Exception $e) {
            return $this->error('An error occurred while fetching favorite providers', 500, [
                'exception' => $e->getMessage()
            ]);
        }
    }

    // ===================== FAQs =====================
    public function faqs_list()
    {
        try {
            $faqs = FaqModel::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'question', 'answer', 'sort_order', 'is_active', 'created_at', 'updated_at']);

            return $this->success($faqs, 'FAQs fetched successfully');
        } catch (\Exception $e) {
            return $this->error('An error occurred while fetching FAQs', 500, [
                'exception' => $e->getMessage()
            ]);
        }
    }

    // ===================== Support =====================
    public function support_list()
    {
        try {
            $items = SupportItemModel::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'title', 'value', 'type', 'icon', 'sort_order', 'is_active', 'created_at', 'updated_at']);

            $items->transform(function ($item) {
                $item->icon_url = !empty($item->icon)
                    ? asset('uploads/support_items/' . $item->icon)
                    : null;
                return $item;
            });

            return $this->success($items, 'Support items fetched successfully');
        } catch (\Exception $e) {
            return $this->error('An error occurred while fetching support items', 500, [
                'exception' => $e->getMessage()
            ]);
        }
    }

    // public function provider_home()
    // {
    //     try {
    //         $serviceCategories = is_string(auth()->user()->service_category)
    //             ? json_decode(auth()->user()->service_category, true) ?? []
    //             : (auth()->user()->service_category ?? []);


    //         $post_requests = JobRequestModel::with('images', 'category', 'user')
    //             ->where('status', 'pending')
    //             ->where('provider_id', null)
    //             ->whereIn('category_id', $serviceCategories)
    //             ->latest()
    //             ->limit(2)
    //             ->get();

    //         foreach ($post_requests as $request) {
    //             if ($request->images) {
    //                 foreach ($request->images as $image) {
    //                     $image->path = asset('uploads/job_gallery/' . $image->path);
    //                 }
    //             }
    //         }

    //         foreach ($post_requests as $post) {
    //             if ($post->category->path && !str_starts_with($post->category->path, 'http')) {
    //                 $post->category->path = asset('uploads/service_category/' . $post->category->path);
    //             }
    //         }

    //         $direct_hires = JobRequestModel::with('images', 'user', 'category')
    //             ->where('status', 'pending')
    //             ->where('provider_id', auth()->id())
    //             ->latest()
    //             ->limit(2)
    //             ->get();

    //         foreach ($direct_hires as $request) {
    //             if ($request->images) {
    //                 foreach ($request->images as $image) {
    //                     $image->path = asset('uploads/job_gallery/' . $image->path);
    //                 }
    //             }
    //         }

    //         foreach ($direct_hires as $hire) {
    //             if ($hire->category->path && !str_starts_with($hire->category->path, 'http')) {
    //                 $hire->category->path = asset('uploads/service_category/' . $hire->category->path);
    //             }
    //         }


    //         $data = [
    //             'post_requests' => $post_requests,
    //             'direct_hires' => $direct_hires,
    //         ];

    //         return $this->success($data, 'Home page fetched successfully');
    //     } catch (\Exception $e) {
    //         return $this->error('An error occurred while fetching home page', 500, [
    //             'exception' => $e->getMessage()
    //         ]);
    //     }
    // }

    public function provider_home()
    {
        try {
            $serviceCategories = $this->getProviderServiceCategories(auth()->user());

            /**
             * --------------------
             * POST REQUESTS
             * --------------------
             */
            $post_requests = JobRequestModel::with([
                'images',
                'category',
                'user',
                'providerBids'
            ])
                ->where('status', 'pending')
                ->whereNull('provider_id')
                ->whereIn('category_id', $serviceCategories)
                ->latest()
                ->limit(2)
                ->get();

            foreach ($post_requests as $job) {
                foreach ($job->images ?? [] as $image) {
                    $image->path = asset('uploads/job_gallery/' . $image->path);
                }

                if ($job->category && $job->category->path && !str_starts_with($job->category->path, 'http')) {
                    $job->category->path = asset('uploads/service_category/' . $job->category->path);
                }
            }

            /**
             * --------------------
             * DIRECT HIRES
             * --------------------
             */
            $direct_hires = JobRequestModel::with([
                'images',
                'user',
                'category',
                'providerBids'
            ])
                ->where('status', 'pending')
                ->where('provider_id', auth()->id())
                ->whereDoesntHave('bids', function ($query) {
                    $query->where('provider_id', auth()->id())
                        ->where('status', 'accepted');
                })
                ->latest()
                ->limit(2)
                ->get();

            foreach ($direct_hires as $job) {
                foreach ($job->images ?? [] as $image) {
                    $image->path = asset('uploads/job_gallery/' . $image->path);
                }

                if ($job->category && $job->category->path && !str_starts_with($job->category->path, 'http')) {
                    $job->category->path = asset('uploads/service_category/' . $job->category->path);
                }
            }

            return $this->success([
                'post_requests' => $post_requests,
                'direct_hires'  => $direct_hires,
            ], 'Home page fetched successfully');
        } catch (\Exception $e) {
            return $this->error('An error occurred while fetching home page', 500, [
                'exception' => $e->getMessage()
            ]);
        }
    }


    public function view_all_post_requests()
    {
        try {
            $serviceCategories = $this->getProviderServiceCategories(auth()->user());

            $post_requests = JobRequestModel::with('user', 'images', 'category')
                ->where('status', 'pending')
                ->where('provider_id', null)
                ->whereIn('category_id', $serviceCategories)
                ->latest()
                ->get();

            foreach ($post_requests as $request) {
                if ($request->images) {
                    foreach ($request->images as $image) {
                        $image->path = asset('uploads/job_gallery/' . $image->path);
                    }
                }
            }

            return $this->success($post_requests, 'Post requests fetched successfully');
        } catch (\Exception $e) {
            return $this->error('An error occurred while fetching post requests', 500, [
                'exception' => $e->getMessage()
            ]);
        }
    }

    public function getJobDetail($id)
    {
        try {
            $job = JobRequestModel::with([
                'images',
                'user',
                'category',
                'providerBids'
            ])->where('id', $id)->first();

            if (!$job) {
                return $this->error('Job not found', 404);
            }

            foreach ($job->images ?? [] as $image) {
                $image->path = asset('uploads/job_gallery/' . $image->path);
            }

            if ($job->category && $job->category->path && !str_starts_with($job->category->path, 'http')) {
                $job->category->path = asset('uploads/service_category/' . $job->category->path);
            }

            return $this->success($job, 'Job detail fetched successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to fetch job detail', 500, [
                'error' => $e->getMessage()
            ]);
        }
    }


    // public function view_all_direct_requests()
    // {
    //     try {
    //         $direct_hires = JobRequestModel::with('user', 'category', 'images')
    //             ->where('status', 'pending')
    //             ->where('provider_id', auth()->id())
    //             ->latest()
    //             ->get();

    //         foreach ($direct_hires as $hire) {
    //             // $hire->category->path = $hire->category->path ? asset('uploads/service_category/' . $hire->category->path) : asset('assets/img/default.jpg');
    //             if ($hire->category->path && !str_starts_with($hire->category->path, 'http')) {
    //                 $hire->category->path = asset('uploads/service_category/' . $hire->category->path);
    //             }
    //         }
    //         foreach ($direct_hires as $request) {
    //             if ($request->images) {
    //                 foreach ($request->images as $image) {
    //                     $image->path = asset('uploads/job_gallery/' . $image->path);
    //                 }
    //             }
    //         }

    //         return $this->success($direct_hires, 'Direct requests fetched successfully');
    //     } catch (\Exception $e) {
    //         return $this->error('An error occurred while fetching direct requests', 500, [
    //             'exception' => $e->getMessage()
    //         ]);
    //     }
    // }

    public function view_all_direct_requests()
    {
        try {
            $direct_hires = JobRequestModel::with([
                'user',
                'category',
                'images',
                'providerBids'
            ])
                ->whereIn('status', ['pending', 'rejected'])
                ->where('provider_id', auth()->id())
                ->whereDoesntHave('bids', function ($query) {
                    $query->where('provider_id', auth()->id())
                        ->where('status', 'accepted');
                })
                ->latest()
                ->get();

            foreach ($direct_hires as $job) {

                // Images
                foreach ($job->images ?? [] as $image) {
                    $image->path = asset('uploads/job_gallery/' . $image->path);
                }

                // Category image
                if ($job->category && $job->category->path && !str_starts_with($job->category->path, 'http')) {
                    $job->category->path = asset('uploads/service_category/' . $job->category->path);
                }
            }

            return $this->success($direct_hires, 'Direct requests fetched successfully');
        } catch (\Exception $e) {
            return $this->error('An error occurred while fetching direct requests', 500, [
                'exception' => $e->getMessage()
            ]);
        }
    }


    // public function accept_reject_request(Request $request)
    // {
    //     $request->validate([
    //         'request_id' => 'required|exists:jobss,id',
    //         'status' => 'required|in:accepted,rejected',
    //         'price' => 'required_if:status,accepted|numeric|min:0',
    //         'time' => 'required_if:status,accepted',
    //         'date' => 'required_if:status,accepted',
    //     ]);
    //     try {
    //         $jobRequest = JobRequestModel::findOrFail($request->request_id);
    //         $jobRequest->job_date = $request->date;
    //         $jobRequest->job_time = $request->time;
    //         $jobRequest->price = $request->price;
    //         $jobRequest->status = $request->status == 'accepted' ? 'quoted' : 'cancelled';
    //         $jobRequest->save();
    //         if ($request->status == 'accepted') {
    //             $order = new Orders();
    //             $order->user_id = $jobRequest->user_id;
    //             $order->provider_id = auth()->id();
    //             $order->job_id = $jobRequest->id;
    //             $order->price = $request->price;
    //             $order->source = 'direct_hiring';
    //             $order->address = $jobRequest->address;
    //             $order->details = $jobRequest->description;
    //             $order->status = 'pending';
    //             $order->save();
    //         }

    //         return $this->success(null, 'Request status updated successfully');
    //     } catch (\Exception $e) {
    //         return $this->error('An error occurred while updating request status', 500, [
    //             'exception' => $e->getMessage()
    //         ]);
    //     }
    // }

    public function accept_reject_request(Request $request)
    {
        $request->validate([
            'request_id' => 'required|exists:jobss,id',
            'status' => 'required|in:accepted,rejected',
            'price' => 'required_if:status,accepted|numeric|min:0',
            'time' => 'nullable|string',
            'date' => 'nullable|string',
        ]);
        try {
            $provider = auth('sanctum')->user();
            if (!$provider) {
                return $this->error('Unauthorized.', 401);
            }

            $jobRequest = JobRequestModel::findOrFail($request->request_id);

            if ((int) $jobRequest->provider_id !== (int) $provider->id) {
                return $this->error('You are not allowed to update this request.', 403);
            }

            $jobRequest->price = $request->price;
            $jobRequest->status = $request->status == 'accepted' ? 'pending' : 'cancelled';
            $jobRequest->save();

            $bid = new BidModel();
            $bid->job_id = $jobRequest->id;
            $bid->provider_id = $provider->id;
            $bid->price = $request->price;
            $bid->bid_time = $request->time;
            $bid->bid_date = $request->date;
            $bid->status = $request->status == 'accepted' ? 'pending' : 'rejected';
            $bid->save();

            $customer = User::find($jobRequest->user_id);
            if ($customer) {
                try {
                    $customer->notify((new ProviderRespondedToRequestNotification($jobRequest, $provider, $request->status))->afterCommit());
                } catch (\Throwable $notificationException) {
                    Log::error('Failed to send provider response notification: ' . $notificationException->getMessage());
                }
            }

            return $this->success(null, 'Request status updated successfully');
        } catch (\Exception $e) {
            return $this->error('An error occurred while updating request status', 500, [
                'exception' => $e->getMessage()
            ]);
        }
    }

    public function my_orders()
    {
        try {
            $user = auth('sanctum')->user();

            $statuses = [
                'ongoing_orders' => ['arrived', 'on_the_way', 'working', 'provider_completed'],
                'completed_orders' => ['completed'],
                'scheduled_orders' => ['pending'],
                'cancelled_orders' => ['cancelled'],
            ];

            $data = [];

            foreach ($statuses as $key => $status) {
                $orders = Orders::with(['job.category', 'user'])
                    ->where('provider_id', $user->id)
                    ->whereIn('status', (array) $status)
                    ->orderBy('id', 'DESC')
                    ->get();

                foreach ($orders as $order) {
                    $category = $order->job->category ?? null;
                    if ($category) {
                        $category->path = $category->path
                            ? asset('uploads/service_category/' . $category->path)
                            : asset('assets/img/default.jpg');
                    }
                }

                $data[$key] = $orders;
            }

            return $this->success($data, 'My orders loaded successfully.');
        } catch (\Throwable $e) {
            Log::error('Error in my_orders: ' . $e->getMessage());
            return $this->error('Failed to load my orders.', 500);
        }
    }

    public function cancel_order($id)
    {
        try {
            $order = Orders::where('id', $id)->first();
            if (!$order) {
                return $this->notFound('Order not found');
            }

            if ($order->status == 'pending') {
                $order->status = 'cancelled';
                $order->save();

                $canceller = auth('sanctum')->user();
                if (!$canceller) {
                    return $this->error('Unauthorized.', 401);
                }

                if ((int) $canceller->id === (int) $order->user_id) {
                    if ((int) $canceller->role !== 0) {
                        return $this->error('Only customers can cancel their own order.', 403);
                    }
                    $recipient = User::find($order->provider_id);
                } elseif ((int) $canceller->id === (int) $order->provider_id) {
                    if ((int) $canceller->role !== 1) {
                        return $this->error('Only providers can cancel their assigned order.', 403);
                    }
                    $recipient = User::find($order->user_id);
                } else {
                    return $this->error('You are not allowed to cancel this order.', 403);
                }

                if ($recipient) {
                    try {
                        $recipient->notify((new OrderCancelledByCustomerNotification($order, $canceller))->afterCommit());
                    } catch (\Throwable $notificationException) {
                        Log::error('Failed to send order cancelled notification: ' . $notificationException->getMessage());
                    }
                }

                return $this->success(null, 'Order cancelled successfully.');
            } else {
                return $this->error('Only scheduled orders can be cancelled.');
            }
        } catch (\Exception $e) {
            Log::error('Error in cancel_order: ' . $e->getMessage());
            return $this->error('Failed to cancel order.', 500);
        }
    }

    public function track_order($id)
    {
        try {
            $tracking = OrderTracking::with('order.user', 'order.provider', 'order.job')
                ->where('order_id', $id)
                ->get();

            if ($tracking->isEmpty()) {
                return $this->notFound('No tracking history found for this order.');
            }

            $order = $tracking->first()->order;

            $order->user->profile_image = $order->user->profile_image
                ? asset('uploads/profile/' . $order->user->profile_image)
                : asset('assets/img/default.jpg');

            $order->provider->profile_image = $order->provider->profile_image
                ? asset('uploads/profile/' . $order->provider->profile_image)
                : asset('assets/img/default.jpg');

            $isCompleted = $order->status === 'completed';

            return $this->success(
                $tracking,
                $isCompleted ? 'Order completed' : 'Order in progress'
            );
        } catch (\Exception $e) {
            Log::error('Error in track_order: ' . $e->getMessage());
            return $this->error('Failed to track order.', 500);
        }
    }


    public function update_order_status($id, Request $request)
    {
        $request->validate([
            'type' => 'nullable|in:accept,reject',
            'status' => [
                'required_without:type',
                'nullable',
                'in:on_the_way,arrived,working,provider_completed,completed',
            ],
            'latitude' => 'required_if:status,on_the_way|nullable',
            'longitude' => 'required_if:status,on_the_way|nullable',
        ]);

        try {
            $order = Orders::where('id', $id)->first();
            if (!$order) {
                return $this->notFound('Order not found');
            }

            $actor = auth('sanctum')->user();
            if (!$actor) {
                return $this->error('Unauthorized.', 401);
            }

            $isCustomerActor = (int) $actor->role === 0 && (int) $actor->id === (int) $order->user_id;
            $isProviderActor = (int) $actor->role === 1 && (int) $actor->id === (int) $order->provider_id;

            if (!$isCustomerActor && !$isProviderActor) {
                return $this->error('Only the assigned customer or provider can update this order status.', 403);
            }

            if ($request->filled('type')) {
                if ($request->type === 'accept') {
                    $order->status = 'completed';
                } elseif ($request->type === 'reject') {
                    $order->status = 'working';
                }
            } else {
                $order->status = $request->status;
            }
            $order->save();


            if ($request->filled('type')) {
                // only update existing provider_completed record
                $tracking = OrderTracking::where('order_id', $id)
                    ->where('status', 'provider_completed')
                    ->first();

                if ($tracking && $request->type == 'reject') {
                    $tracking->delete();
                } elseif ($tracking && $request->type == 'accept') {
                    $tracking = new OrderTracking();
                    $tracking->order_id = $id;
                    $tracking->status = $order->status;
                    $tracking->latitude = $request->latitude ?? null;
                    $tracking->longitude = $request->longitude ?? null;
                    $tracking->save();
                }
            } else {
                // normal flow: update or create tracking for the new status
                $tracking = OrderTracking::where('order_id', $id)
                    ->where('status', $order->status)
                    ->first();

                if (!$tracking) {
                    $tracking = new OrderTracking();
                    $tracking->order_id = $id;
                }

                $tracking->status = $order->status;
                $tracking->latitude = $request->latitude ?? null;
                $tracking->longitude = $request->longitude ?? null;
                $tracking->save();
            }

            $recipient = $isProviderActor
                ? User::find($order->user_id)
                : User::find($order->provider_id);

            if ($recipient) {
                try {
                    $recipient->notify((new OrderStatusUpdatedNotification($order, $actor, $order->status))->afterCommit());
                } catch (\Throwable $notificationException) {
                    Log::error('Failed to send order status updated notification: ' . $notificationException->getMessage());
                }
            }


            return $this->success(null, 'Order status updated successfully.');
        } catch (\Exception $e) {
            Log::error('Error in update_order_status: ' . $e->getMessage());
            return $this->error('Failed to update order status.', 500);
        }
    }

    public function service_requests()
    {
        try {

            $requests = JobRequestModel::with(['category', 'images'])->where('status', 'pending')->get();

            foreach ($requests as $request) {
                foreach ($request->images as $image) {
                    $image->path = $image->path != null ? asset('uploads/job_gallery/' . $image->path) : asset('assets/img/default.jpg');
                }
            }

            return $this->success($requests);
        } catch (\Throwable $e) {
            Log::error('Error in getting service requests: ' . $e->getMessage());
            return $this->error('Failed to load service requests.', 500);
        }
    }

    public function service_request_details($id)
    {
        try {


            $request = JobRequestModel::with(['category', 'images'])->where('status', '!=', ['completed', 'cancelled'])->where('id', $id)->get();


            foreach ($request->images as $image) {
                $image->path = $image->path != null ? asset('uploads/job_gallery/' . $image->path) : asset('assets/img/default.jpg');
            }


            return $this->success($request);
        } catch (\Throwable $e) {
            Log::error('Error in getting service requests: ' . $e->getMessage());
            return $this->error('Failed to load service requests.', 500);
        }
    }

    public function post_bid($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'details' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'time' => 'nullable|date_format:H:i',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 'Validation failed.');
        }

        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return $this->error('Unauthorized.', 401);
            }

            if ((int) $user->role !== 1) {
                return $this->error('Only providers can submit bids.', 403);
            }

            $job = JobRequestModel::where('id', $id)->first();
            if (!$job) {
                return $this->notFound('Job request not found.');
            }
            if ($job->status != 'pending') {
                return $this->error('This job request is not available for bidding.', 400);
            }

            $bid = BidModel::where('job_id', $id)
                ->where('provider_id', auth()->id())
                ->first();

            if ($bid && $bid->status !== 'rejected') {
                return $this->error('You have already submitted a bid for this job.', 400);
            }


            DB::beginTransaction();

            if ($job->provider_id) {
                return $this->error('This job has already been assigned to a provider.', 400);
            }

            $bid =  new BidModel;
            $bid->job_id = $id;
            $bid->provider_id = $user->id;
            $bid->bid_details = $request->details;
            $bid->price = $request->price;
            $bid->bid_time = $request->time;
            $bid->status = 'pending';
            $bid->save();

            DB::commit();

            $jobOwner = User::find($job->user_id);
            if ($jobOwner) {
                try {
                    $jobOwner->notify((new BidReceivedNotification($job, $user))->afterCommit());
                } catch (\Throwable $notificationException) {
                    Log::error('Failed to send bid notification: ' . $notificationException->getMessage());
                }
            }

            return $this->success(null, 'Bid submitted successfully.');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error in post_bid: ' . $e->getMessage());
            return $this->error('An error occurred while submitting the bid.', 500);
        }
    }

    public function my_bids(Request $request)
    {
        try {
            $user = auth()->user();
            $bids = BidModel::with(['job.category', 'job.user', 'order'])
                ->where('provider_id', $user->id)->get();

            if ($bids->isEmpty()) {
                return $this->notFound('No bids found for this provider.');
            }

            foreach ($bids as $bid) {
                if ($bid->job->category) {
                    $bid->job->category->path = $bid->job->category->path ? asset('uploads/service_category/' . $bid->job->category->path) : asset('assets/img/default.jpg');
                }
            }

            return $this->success($bids, 'My bids fetched successfully.');
        } catch (\Exception $e) {
            Log::error('Error in my_bids: ' . $e->getMessage());
            return $this->error('Failed to load my bids.', 500);
        }
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

    private function getProviderServiceCategories(User $user): array
    {
        return $this->resolveCategoryIds(optional($user->providerProfile)->service_category);
    }

    private function decorateProvider(User $provider): User
    {
        $provider->loadMissing('providerProfile');

        $provider->profile_image = !empty($provider->profile_image)
            ? asset('uploads/profile_images/' . $provider->profile_image)
            : asset('assets/img/default.jpg');

        $provider->service_category = optional($provider->providerProfile)->service_category ?? [];
        $provider->provider_type = optional($provider->providerProfile)->provider_type;
        $provider->company_name = optional($provider->providerProfile)->company_name;
        $provider->address = optional($provider->providerProfile)->address;
        $provider->latitude = optional($provider->providerProfile)->latitude;
        $provider->longitude = optional($provider->providerProfile)->longitude;
        $provider->bio = optional($provider->providerProfile)->bio;
        $provider->charge_type = optional($provider->providerProfile)->charge_type;
        $provider->charge_amount = optional($provider->providerProfile)->charge_amount;
        $provider->display_name = optional($provider->providerProfile)->provider_type === 'company'
            ? (optional($provider->providerProfile)->company_name ?? $provider->name)
            : $provider->name;

        return $provider;
    }

    private function decorateMarketplace(User $marketplace): User
    {
        $marketplace->loadMissing('marketplaceProfile');

        $marketplace->service_category = optional($marketplace->marketplaceProfile)->service_category ?? [];
        $marketplace->shop_title = optional($marketplace->marketplaceProfile)->shop_title;
        $marketplace->shop_logo = optional($marketplace->marketplaceProfile)->shop_logo;
        $marketplace->shop_image = !empty(optional($marketplace->marketplaceProfile)->shop_logo)
            ? asset('uploads/shop_logos/' . $marketplace->marketplaceProfile->shop_logo)
            : asset('assets/img/default.jpg');

        return $marketplace;
    }
}
