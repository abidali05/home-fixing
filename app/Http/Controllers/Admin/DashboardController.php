<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $totalUsers = DB::table('users')->count();
        $totalProviders = DB::table('provider_profiles')->distinct()->count('user_id');
        $totalSellers = DB::table('marketplace_profiles')->distinct()->count('user_id');
        $totalServiceCategories = DB::table('categories')->count();
        $totalServiceRequests = DB::table('jobss')->count();
        $totalMarketplaceOrders = DB::table('marketplace_orders')->count();
        $totalProducts = DB::table('products')->count();
        $totalCampaigns = DB::table('campaigns')->count();

        $totalOrdersPrice = (float) DB::table('orders')
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->sum('price');

        $totalToPayToSystem = (float) DB::table('orders')
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->where('paid_to_system', 0)
            ->sum('price');

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
            $growthSeries['users'][] = DB::table('users')
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->count();

            $growthSeries['providers'][] = DB::table('provider_profiles')
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->count();

            $growthSeries['sellers'][] = DB::table('marketplace_profiles')
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->count();

            $growthSeries['requests'][] = DB::table('jobss')
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->count();
        }

        $serviceRevenueChart = [
            'labels' => ['Collected Revenue', 'Pending To System'],
            'values' => [
                round(max($totalOrdersPrice - $totalToPayToSystem, 0), 2),
                round($totalToPayToSystem, 2),
            ],
        ];

        $marketplaceStatusChart = [
            'labels' => ['Pending Flow', 'Completed', 'Rejected/Cancelled'],
            'values' => [
                $pendingMarketplaceOrders,
                DB::table('marketplace_orders')->where('status', 'completed')->count(),
                DB::table('marketplace_orders')->whereIn('status', ['reject', 'cancelled'])->count(),
            ],
        ];

        $recentMarketplaceOrders = DB::table('marketplace_orders as mo')
            ->leftJoin('users as customers', 'customers.id', '=', 'mo.user_id')
            ->select('mo.id', 'mo.order_number', 'mo.total_amount', 'mo.status', 'mo.payment_status', 'mo.created_at', 'customers.name as customer_name')
            ->latest('mo.id')
            ->limit(6)
            ->get();

        $topSellers = DB::table('marketplace_profiles as mp')
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

        return view('dashboard', [
            'cards' => $cards,
            'financialSummary' => [
                'service_revenue' => $totalOrdersPrice,
                'pending_to_system' => $totalToPayToSystem,
                'marketplace_revenue' => $totalMarketplaceRevenue,
                'active_campaigns' => $activeCampaigns,
            ],
            'growthChart' => [
                'labels' => $monthlyLabels,
                'series' => $growthSeries,
            ],
            'serviceRevenueChart' => $serviceRevenueChart,
            'marketplaceStatusChart' => $marketplaceStatusChart,
            'recentMarketplaceOrders' => $recentMarketplaceOrders,
            'topSellers' => $topSellers,
        ]);
    }
}
