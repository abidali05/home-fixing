<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user() ?? auth('admin')->user();
        $isCompany = $user && $user->is_company;

        if ($isCompany) {
            $assignedProviderIds = DB::table('users')
                ->where('company_id', $user->id)
                ->where(function ($b) {
                    $b->where('role', '1')
                        ->orWhere('role', 1)
                        ->orWhere('has_roles', '1')
                        ->orWhere('has_roles', 1)
                        ->orWhere('has_roles', 'like', '%1%')
                        ->orWhereRaw("FIND_IN_SET('1', has_roles)")
                        ->orWhereIn('id', DB::table('provider_profiles')->pluck('user_id'));
                })
                ->pluck('id')
                ->toArray();
            
            $totalUsers = DB::table('orders')
                ->whereIn('provider_id', $assignedProviderIds)
                ->distinct()
                ->count('user_id');
            $totalProviders = count($assignedProviderIds);
            $totalSellers = 0;
            $totalServiceCategories = 0;
            $totalServiceRequests = DB::table('orders')
                ->whereIn('provider_id', $assignedProviderIds)
                ->count();
            $totalMarketplaceOrders = 0;
            $totalProducts = 0;
            $totalCampaigns = 0;

            $companyCompletedOrders = DB::table('orders')
                ->whereIn('provider_id', $assignedProviderIds)
                ->whereIn('status', ['completed', 'delivered'])
                ->get();

            $settings = DB::table('system_settings')->first();
            $customerAppFeeSetting = (float) ($settings->customer_app_fee ?? 3.00);
            $azhlPercentageSetting = (float) ($settings->azhl_percentage ?? 10.00);
            $gatewayFeePctSetting = (float) ($settings->payment_gateway_fee_percentage ?? 2.50);
            $gatewayFixedFeeSetting = (float) ($settings->payment_gateway_fixed_fee ?? 1.00);
            $gatewayVatPctSetting = (float) ($settings->payment_gateway_vat_percentage ?? 15.00);

            $totalOrdersPrice = 0.0;
            foreach ($companyCompletedOrders as $ord) {
                $repairPrice = (float) ($ord->price ?? 0);
                if (!empty($ord->job_id)) {
                    $acceptedBid = DB::table('bids')->where('job_id', $ord->job_id)->whereIn('status', ['accepted', 'completed', 'hired', 'cancelled'])->first();
                    if ($acceptedBid && (float) $acceptedBid->price > 0) {
                        $repairPrice = (float) $acceptedBid->price;
                    }
                }
                if ($repairPrice > 103) {
                    $approxSubtotal = ($repairPrice - $gatewayFixedFeeSetting * (1 + $gatewayVatPctSetting / 100)) / (1 + ($gatewayFeePctSetting / 100) * (1 + $gatewayVatPctSetting / 100));
                    $estimatedRepair = max(0, $approxSubtotal - $customerAppFeeSetting);
                    $repairPrice = abs($estimatedRepair - round($estimatedRepair)) < 0.1 ? (float) round($estimatedRepair) : (float) round($estimatedRepair, 2);
                }
                $azhlFee = $repairPrice * ($azhlPercentageSetting / 100);
                $totalOrdersPrice += max(0, $repairPrice - $azhlFee);
            }

            $totalMarketplaceRevenue = 0.0;
            $pendingMarketplaceOrders = 0;
            $activeCampaigns = 0;

            $cards = [
                [
                    'label' => 'Total Clients',
                    'value' => number_format($totalUsers),
                    'meta' => 'Customers served',
                    'icon' => 'bi-people',
                    'tone' => 'sky',
                ],
                [
                    'label' => 'My Providers',
                    'value' => number_format($totalProviders),
                    'meta' => 'Assigned staff',
                    'icon' => 'bi-briefcase',
                    'tone' => 'mint',
                ],
                [
                    'label' => 'Fulfilled Requests',
                    'value' => number_format($totalServiceRequests),
                    'meta' => 'Total services',
                    'icon' => 'bi-send',
                    'tone' => 'coral',
                ],
                [
                    'label' => 'Total Earnings',
                    'value' => number_format($totalOrdersPrice) . ' SAR',
                    'meta' => 'Gross revenue',
                    'icon' => 'bi-currency-dollar',
                    'tone' => 'amber',
                ],
            ];
        } else {
            $totalUsers = DB::table('users')->count();
            $totalProviders = DB::table('users')
                ->where(function ($b) {
                    $b->where('role', '1')
                        ->orWhere('role', 1)
                        ->orWhere('has_roles', '1')
                        ->orWhere('has_roles', 1)
                        ->orWhere('has_roles', 'like', '%1%')
                        ->orWhereRaw("FIND_IN_SET('1', has_roles)")
                        ->orWhereIn('id', DB::table('provider_profiles')->pluck('user_id'));
                })
                ->count();
            $totalSellers = DB::table('users')
                ->where(function ($b) {
                    $b->where('role', '2')
                        ->orWhere('role', 2)
                        ->orWhere('has_roles', '2')
                        ->orWhere('has_roles', 2)
                        ->orWhere('has_roles', 'like', '%2%')
                        ->orWhereRaw("FIND_IN_SET('2', has_roles)");
                })
                ->count();
            $totalServiceCategories = DB::table('categories')->count();
            $totalServiceRequests = DB::table('jobss')->count();
            $totalMarketplaceOrders = DB::table('marketplace_orders')->count();
            $totalProducts = DB::table('products')->count();
            $totalCampaigns = DB::table('campaigns')->count();

            $settings = DB::table('system_settings')->first();
            $customerAppFeeSetting = (float) ($settings->customer_app_fee ?? 3.00);
            $azhlPercentageSetting = (float) ($settings->azhl_percentage ?? 10.00);
            $gatewayFeePctSetting = (float) ($settings->payment_gateway_fee_percentage ?? 2.50);
            $gatewayFixedFeeSetting = (float) ($settings->payment_gateway_fixed_fee ?? 1.00);
            $gatewayVatPctSetting = (float) ($settings->payment_gateway_vat_percentage ?? 15.00);

            $completedOrders = DB::table('orders')
                ->whereIn('status', ['completed', 'delivered'])
                ->get();

            $totalCustomerPaidVolume = 0.0;
            $totalNetRepairVolume = 0.0;
            $totalProviderNetEarnings = 0.0;
            $totalNetAzhlAppProfit = 0.0;
            $totalGatewayFeesWithVat = 0.0;

            foreach ($completedOrders as $ord) {
                $repairPrice = (float) ($ord->price ?? 0);
                if (!empty($ord->job_id)) {
                    $acceptedBid = DB::table('bids')
                        ->where('job_id', $ord->job_id)
                        ->whereIn('status', ['accepted', 'completed', 'hired', 'cancelled'])
                        ->first();
                    if ($acceptedBid && (float) $acceptedBid->price > 0) {
                        $repairPrice = (float) $acceptedBid->price;
                    }
                }

                if ($repairPrice > 103) {
                    $approxSubtotal = ($repairPrice - $gatewayFixedFeeSetting * (1 + $gatewayVatPctSetting / 100)) / (1 + ($gatewayFeePctSetting / 100) * (1 + $gatewayVatPctSetting / 100));
                    $estimatedRepair = max(0, $approxSubtotal - $customerAppFeeSetting);
                    $repairPrice = abs($estimatedRepair - round($estimatedRepair)) < 0.1 ? (float) round($estimatedRepair) : (float) round($estimatedRepair, 2);
                }

                $subtotal = $repairPrice + $customerAppFeeSetting;
                $gatewaySubtotal = ($subtotal * ($gatewayFeePctSetting / 100)) + $gatewayFixedFeeSetting;
                $gatewayVat = $gatewaySubtotal * ($gatewayVatPctSetting / 100);
                $totalGatewayFee = $gatewaySubtotal + $gatewayVat;
                $totalCustomerPaid = $repairPrice + $customerAppFeeSetting + $totalGatewayFee;

                $azhlFee = $repairPrice * ($azhlPercentageSetting / 100);
                $providerNet = max(0, $repairPrice - $azhlFee);

                $totalCustomerPaidVolume += $totalCustomerPaid;
                $totalNetRepairVolume += $repairPrice;
                $totalProviderNetEarnings += $providerNet;
                $totalNetAzhlAppProfit += ($customerAppFeeSetting + $azhlFee);
                $totalGatewayFeesWithVat += $totalGatewayFee;
            }

            $totalOrdersPrice = $totalProviderNetEarnings;

            $totalMarketplaceRevenue = (float) DB::table('marketplace_orders')
                ->whereNotIn('status', ['reject', 'cancelled'])
                ->sum('total_amount');

            $pendingMarketplaceOrders = DB::table('marketplace_orders')
                ->whereIn('status', ['pending', 'accept', 'processing', 'mark_as_shipped', 'mark_as_delivered'])
                ->count();

            $activeCampaigns = DB::table('campaigns')
                ->where('status', 'active')
                ->whereDate('end_date', '>=', now()->toDateString())
                ->count();

            $cards = [
                [
                    'label' => 'Total Users',
                    'value' => number_format($totalUsers),
                    'meta' => 'Customer accounts',
                    'icon' => 'bi-people',
                    'tone' => 'sky',
                ],
                [
                    'label' => 'Providers',
                    'value' => number_format($totalProviders),
                    'meta' => 'Service professionals',
                    'icon' => 'bi-briefcase',
                    'tone' => 'mint',
                ],
                [
                    'label' => 'Sellers',
                    'value' => number_format($totalSellers),
                    'meta' => 'Marketplace stores',
                    'icon' => 'bi-shop',
                    'tone' => 'violet',
                ],
                [
                    'label' => 'Categories',
                    'value' => number_format($totalServiceCategories),
                    'meta' => 'Service categories',
                    'icon' => 'bi-grid',
                    'tone' => 'amber',
                ],
                [
                    'label' => 'Requests',
                    'value' => number_format($totalServiceRequests),
                    'meta' => 'Service requests',
                    'icon' => 'bi-send',
                    'tone' => 'coral',
                ],
                [
                    'label' => 'Marketplace Orders',
                    'value' => number_format($totalMarketplaceOrders),
                    'meta' => 'All marketplace orders',
                    'icon' => 'bi-bag-check',
                    'tone' => 'indigo',
                ],
                [
                    'label' => 'Products',
                    'value' => number_format($totalProducts),
                    'meta' => 'Marketplace inventory',
                    'icon' => 'bi-box-seam',
                    'tone' => 'slate',
                ],
                [
                    'label' => 'Campaigns',
                    'value' => number_format($totalCampaigns),
                    'meta' => $activeCampaigns . ' active now',
                    'icon' => 'bi-megaphone',
                    'tone' => 'rose',
                ],
            ];
        }

        $monthlyLabels = collect(range(5, 0))
            ->map(fn($offset) => now()->subMonths($offset)->format('M Y'))
            ->values();

        $monthlyRange = collect(range(5, 0))->map(function ($offset) {
            $date = now()->subMonths($offset);

            return [
                'label' => $date->format('M Y'),
                'start' => $date->copy()->startOfMonth(),
                'end' => $date->copy()->endOfMonth(),
            ];
        })->values();

        $growthSeries = [
            'users' => [],
            'providers' => [],
            'sellers' => [],
            'requests' => [],
        ];

        foreach ($monthlyRange as $range) {
            if ($isCompany) {
                $growthSeries['users'][] = DB::table('orders')
                    ->whereIn('provider_id', $assignedProviderIds)
                    ->whereBetween('created_at', [$range['start'], $range['end']])
                    ->distinct()
                    ->count('user_id');

                $growthSeries['providers'][] = DB::table('users')
                    ->where('company_id', $user->id)
                    ->where(function ($b) {
                        $b->where('role', '1')
                            ->orWhere('role', 1)
                            ->orWhere('has_roles', '1')
                            ->orWhere('has_roles', 1)
                            ->orWhere('has_roles', 'like', '%1%')
                            ->orWhereRaw("FIND_IN_SET('1', has_roles)")
                            ->orWhereIn('id', DB::table('provider_profiles')->pluck('user_id'));
                    })
                    ->whereBetween('created_at', [$range['start'], $range['end']])
                    ->count();

                $growthSeries['sellers'][] = 0;

                $growthSeries['requests'][] = DB::table('orders')
                    ->whereIn('provider_id', $assignedProviderIds)
                    ->whereBetween('created_at', [$range['start'], $range['end']])
                    ->count();
            } else {
                $growthSeries['users'][] = DB::table('users')
                    ->whereBetween('created_at', [$range['start'], $range['end']])
                    ->count();

                $growthSeries['providers'][] = DB::table('users')
                    ->where(function ($b) {
                        $b->where('role', '1')
                            ->orWhere('role', 1)
                            ->orWhere('has_roles', '1')
                            ->orWhere('has_roles', 1)
                            ->orWhere('has_roles', 'like', '%1%')
                            ->orWhereRaw("FIND_IN_SET('1', has_roles)")
                            ->orWhereIn('id', DB::table('provider_profiles')->pluck('user_id'));
                    })
                    ->whereBetween('created_at', [$range['start'], $range['end']])
                    ->count();

                $growthSeries['sellers'][] = DB::table('marketplace_profiles')
                    ->whereBetween('created_at', [$range['start'], $range['end']])
                    ->count();

                $growthSeries['requests'][] = DB::table('jobss')
                    ->whereBetween('created_at', [$range['start'], $range['end']])
                    ->count();
            }
        }

        $serviceRevenueChart = [
            'labels' => ['Completed Service Revenue'],
            'values' => [
                round($totalOrdersPrice, 2),
            ],
        ];

        $marketplaceStatusChart = [
            'labels' => ['Pending Flow', 'Completed', 'Rejected/Cancelled'],
            'values' => $isCompany ? [0, 0, 0] : [
                $pendingMarketplaceOrders,
                DB::table('marketplace_orders')->where('status', 'completed')->count(),
                DB::table('marketplace_orders')->whereIn('status', ['reject', 'cancelled'])->count(),
            ],
        ];

        $recentMarketplaceOrders = $isCompany ? collect() : DB::table('marketplace_orders as mo')
            ->leftJoin('users as customers', 'customers.id', '=', 'mo.user_id')
            ->select('mo.id', 'mo.order_number', 'mo.total_amount', 'mo.status', 'mo.payment_status', 'mo.created_at', 'customers.name as customer_name')
            ->latest('mo.id')
            ->limit(6)
            ->get();

        $topSellers = $isCompany ? collect() : DB::table('marketplace_profiles as mp')
            ->leftJoin('users as u', 'u.id', '=', 'mp.user_id')
            ->leftJoin('products as p', 'p.user_id', '=', 'mp.user_id')
            ->leftJoin('marketplace_order_items as moi', 'moi.shop_id', '=', 'mp.user_id')
            ->select(
                'mp.user_id',
                'mp.shop_title',
                'u.name',
                DB::raw('COUNT(DISTINCT p.id) as products_count'),
                DB::raw('COUNT(DISTINCT moi.marketplace_order_id) as orders_count')
            )
            ->groupBy('mp.user_id', 'mp.shop_title', 'u.name')
            ->orderByDesc('orders_count')
            ->limit(5)
            ->get();

        $topProvidersBidsQuery = DB::table('bids')
            ->join('users', 'users.id', '=', 'bids.provider_id')
            ->select('bids.provider_id', 'users.name', 'users.phone', DB::raw('COUNT(bids.id) as bids_count'));

        if ($isCompany) {
            $topProvidersBidsQuery->whereIn('bids.provider_id', $assignedProviderIds);
        }

        $topProvidersBids = $topProvidersBidsQuery
            ->groupBy('bids.provider_id', 'users.name', 'users.phone')
            ->orderByDesc('bids_count')
            ->limit(5)
            ->get();

        $topProvidersTasksQuery = DB::table('orders')
            ->join('users', 'users.id', '=', 'orders.provider_id')
            ->where('orders.status', 'completed');

        if ($isCompany) {
            $topProvidersTasksQuery->whereIn('orders.provider_id', $assignedProviderIds);
        }

        $topProvidersTasks = $topProvidersTasksQuery
            ->select('orders.provider_id', 'users.name', 'users.phone', DB::raw('COUNT(orders.id) as tasks_count'))
            ->groupBy('orders.provider_id', 'users.name', 'users.phone')
            ->orderByDesc('tasks_count')
            ->limit(5)
            ->get();

        $topMarketplacesOrders = DB::table('marketplace_order_items as moi')
            ->join('users', 'users.id', '=', 'moi.shop_id')
            ->join('marketplace_profiles as mp', 'mp.user_id', '=', 'moi.shop_id')
            ->select('moi.shop_id', 'mp.shop_title', 'users.name', DB::raw('COUNT(moi.id) as orders_count'))
            ->groupBy('moi.shop_id', 'mp.shop_title', 'users.name')
            ->orderByDesc('orders_count')
            ->limit(5)
            ->get();

        $topRatedProviders = DB::table('ratings')
            ->join('users', 'users.id', '=', 'ratings.provider_id')
            ->select('ratings.provider_id', 'users.name', 'users.phone', DB::raw('ROUND(AVG(ratings.rating), 1) as avg_rating'))
            ->groupBy('ratings.provider_id', 'users.name', 'users.phone')
            ->orderByDesc('avg_rating')
            ->limit(5)
            ->get();

        $topRatedMarketplaces = DB::table('marketplace_shop_reviews as msr')
            ->join('users', 'users.id', '=', 'msr.shop_id')
            ->join('marketplace_profiles as mp', 'mp.user_id', '=', 'msr.shop_id')
            ->select('msr.shop_id', 'mp.shop_title', 'users.name', DB::raw('ROUND(AVG(msr.rating), 1) as avg_rating'))
            ->groupBy('msr.shop_id', 'mp.shop_title', 'users.name')
            ->orderByDesc('avg_rating')
            ->limit(5)
            ->get();

        $registeredMarketplaces = DB::table('marketplace_profiles as mp')
            ->join('users', 'users.id', '=', 'mp.user_id')
            ->select('mp.id', 'mp.shop_title', 'users.name as owner_name', 'mp.created_at', 'mp.expires_at')
            ->orderByDesc('mp.created_at')
            ->get();

        $topReferrers = DB::table('provider_profiles')
            ->select(
                'referred_by_id',
                DB::raw('COUNT(id) as total_referrals'),
                DB::raw('MAX(referred_by_code) as referral_code')
            )
            ->whereNotNull('referred_by_id')
            ->groupBy('referred_by_id')
            ->orderByDesc('total_referrals')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                $user = DB::table('users')->where('id', $row->referred_by_id)->first();
                $row->user_name = $user->name ?? 'User #' . $row->referred_by_id;
                $row->user_code = $user->user_code ?? ('AZ' . (1000 + $row->referred_by_id));
                $row->phone = $user->phone ?? '-';
                $row->role = ((string)($user->role ?? 0) === '1') ? 'Provider' : 'Customer';
                return $row;
            });

        return view('dashboard', [
            'cards' => $cards,
            'financialSummary' => [
                'service_revenue' => $totalOrdersPrice,
                'marketplace_revenue' => $totalMarketplaceRevenue,
                'registered_marketplaces' => $totalSellers,
            ],
            'growthChart' => [
                'labels' => $monthlyLabels,
                'series' => $growthSeries,
            ],
            'serviceRevenueChart' => $serviceRevenueChart,
            'marketplaceStatusChart' => $marketplaceStatusChart,
            'recentMarketplaceOrders' => $recentMarketplaceOrders,
            'topSellers' => $topSellers,
            'topProvidersBids' => $topProvidersBids,
            'topProvidersTasks' => $topProvidersTasks,
            'topMarketplacesOrders' => $topMarketplacesOrders,
            'topRatedProviders' => $topRatedProviders,
            'topRatedMarketplaces' => $topRatedMarketplaces,
            'registeredMarketplaces' => $registeredMarketplaces,
            'topReferrers' => $topReferrers,
        ]);
    }

    public function getNotifications()
    {
        $currentUser = auth()->user() ?? auth('admin')->user();
        $isCompany = $currentUser && $currentUser->is_company;
        $companyId = $currentUser ? $currentUser->id : null;

        // Get provider IDs assigned to this company (if company account)
        $assignedProviderIds = [];
        if ($isCompany && $companyId) {
            $assignedProviderIds = DB::table('users')
                ->where('company_id', $companyId)
                ->pluck('id')
                ->toArray();
        }

        $items = [];

        // 1. Service Orders (filtered by company providers if company)
        if (Schema::hasTable('orders')) {
            $query = DB::table('orders')->where('status', 'pending');
            if ($isCompany) {
                if (!empty($assignedProviderIds)) {
                    $query->whereIn('provider_id', $assignedProviderIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            $orders = $query->orderBy('id', 'desc')->limit(4)->get();

            foreach ($orders as $order) {
                $user = DB::table('users')->where('id', $order->user_id)->first();
                $userName = $user->name ?? 'Customer';
                $items[] = [
                    'id' => 'order_' . $order->id,
                    'type' => 'service_order',
                    'title' => "Service Order #{$order->id}",
                    'message' => "{$userName} • SAR " . number_format($order->price ?? 0, 2),
                    'url' => \Illuminate\Support\Facades\Route::has('orders.details') ? route('orders.details', ['id' => $order->id]) : route('orders.index'),
                    'icon' => 'bi-receipt-cutoff',
                    'color' => 'info',
                    'created_at' => $order->created_at ?? now()
                ];
            }
        }

        // 2. Job Requests (filtered by company providers if company)
        if (Schema::hasTable('jobss')) {
            $query = DB::table('jobss')->where('status', 'pending');
            if ($isCompany) {
                if (!empty($assignedProviderIds)) {
                    $query->whereIn('provider_id', $assignedProviderIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            $jobs = $query->orderBy('id', 'desc')->limit(4)->get();

            foreach ($jobs as $job) {
                $user = DB::table('users')->where('id', $job->user_id)->first();
                $userName = $user->name ?? 'Customer';
                $shortDesc = !empty($job->description) ? \Illuminate\Support\Str::limit($job->description, 35) : 'New job request';
                $items[] = [
                    'id' => 'job_' . $job->id,
                    'type' => 'job_request',
                    'title' => "Job Request #{$job->id}",
                    'message' => "{$userName}: {$shortDesc}",
                    'url' => \Illuminate\Support\Facades\Route::has('job_requests.details') ? route('job_requests.details', $job->id) : route('job_requests.index'),
                    'icon' => 'bi-briefcase-fill',
                    'color' => 'primary',
                    'created_at' => $job->created_at ?? now()
                ];
            }
        }

        // 3. Marketplace Orders (Only if super admin)
        if (Schema::hasTable('marketplace_orders') && !$isCompany) {
            $mktOrders = DB::table('marketplace_orders')
                ->where('status', 'pending')
                ->orderBy('id', 'desc')
                ->limit(4)
                ->get();

            foreach ($mktOrders as $mktOrder) {
                $items[] = [
                    'id' => 'mkt_' . $mktOrder->id,
                    'type' => 'marketplace_order',
                    'title' => "Shop Order " . ($mktOrder->order_number ?? "#{$mktOrder->id}"),
                    'message' => "SAR " . number_format($mktOrder->total_amount ?? 0, 2) . " • " . strtoupper($mktOrder->payment_method ?? 'COD'),
                    'url' => \Illuminate\Support\Facades\Route::has('marketplace.orders.show') ? route('marketplace.orders.show', $mktOrder->id) : route('marketplace.orders.index'),
                    'icon' => 'bi-bag-check-fill',
                    'color' => 'success',
                    'created_at' => $mktOrder->created_at ?? now()
                ];
            }
        }

        // 4. System / Direct User Notifications
        if (Schema::hasTable('notifications')) {
            $query = DB::table('notifications');
            if ($isCompany && $companyId) {
                $query->where('user_id', $companyId);
            }
            $systemNotifs = $query->orderBy('id', 'desc')->limit(4)->get();

            foreach ($systemNotifs as $notif) {
                $items[] = [
                    'id' => 'sys_' . $notif->id,
                    'type' => 'system_notification',
                    'title' => $notif->title ?? 'System Alert',
                    'message' => \Illuminate\Support\Str::limit($notif->body ?? '', 40),
                    'url' => \Illuminate\Support\Facades\Route::has('notifications.create') ? route('notifications.create') : '#',
                    'icon' => 'bi-bell-fill',
                    'color' => 'warning',
                    'created_at' => $notif->created_at ?? now()
                ];
            }
        }

        // 5. Account Activation Requests (filtered for company providers if company)
        if (Schema::hasTable('account_active_requests')) {
            $query = DB::table('account_active_requests');
            if ($isCompany) {
                if (!empty($assignedProviderIds)) {
                    $query->whereIn('user_id', $assignedProviderIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            $activations = $query->orderBy('id', 'desc')->limit(3)->get();

            foreach ($activations as $act) {
                $items[] = [
                    'id' => 'act_' . $act->id,
                    'type' => 'activation',
                    'title' => "Activation Request #{$act->id}",
                    'message' => "Provider requesting active status",
                    'url' => route('account_active_requests.index'),
                    'icon' => 'bi-person-check-fill',
                    'color' => 'warning',
                    'created_at' => $act->created_at ?? now()
                ];
            }
        }

        // Sort items by created_at descending
        usort($items, function ($a, $b) {
            return strtotime($b['created_at']) <=> strtotime($a['created_at']);
        });

        return response()->json([
            'success' => true,
            'total' => count($items),
            'items' => array_slice($items, 0, 10)
        ]);
    }
}
