@extends('layouts.app')

@section('title', 'Dashboard')

@push('styles')
    <style>
        .dashboard-hero {
            padding: 1.7rem 1.85rem;
            border: 1px solid #e4ecf2;
            border-radius: 30px;
            background: linear-gradient(135deg, #173042 0%, #214b61 56%, #2aa6ba 100%);
            color: #fff;
            box-shadow: 0 18px 40px rgba(24, 52, 71, 0.12);
        }

        .dashboard-hero-title {
            margin: 0;
            font-size: 1.38rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            color: #ffffff;
        }

        .dashboard-hero-text {
            margin: 0.55rem 0 0;
            max-width: 680px;
            color: rgba(255, 255, 255, 0.76);
            line-height: 1.75;
            font-size: 0.92rem;
        }

        .dashboard-summary-strip {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
            margin-top: 1.55rem;
        }

        .dashboard-summary-chip {
            padding: 1rem 1.05rem;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.11);
            border: 1px solid rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(10px);
        }

        .dashboard-summary-label {
            display: block;
            font-size: 0.71rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 0.4rem;
            font-weight: 600;
        }

        .dashboard-summary-value {
            font-size: 1.04rem;
            font-weight: 700;
        }

        .dashboard-stat-col {
            display: flex;
        }

        .dashboard-stat-card {
            position: relative;
            width: 100%;
            min-height: 158px;
            border: 1px solid #e8eef3;
            border-radius: 24px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            box-shadow: 0 14px 28px rgba(31, 53, 72, 0.05);
            overflow: hidden;
        }

        .dashboard-stat-card::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0) 0%, rgba(246, 250, 252, 0.92) 100%);
            pointer-events: none;
        }

        .dashboard-stat-inner {
            position: relative;
            z-index: 1;
            height: 100%;
            padding: 1.1rem 1.15rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .dashboard-stat-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.9rem;
        }

        .dashboard-stat-label {
            margin: 0;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #6e8190;
        }

        .dashboard-stat-value {
            margin: 0.4rem 0 0;
            font-size: 1.42rem;
            line-height: 1.12;
            font-weight: 700;
            letter-spacing: -0.015em;
            color: #1e3342;
        }

        .dashboard-stat-meta {
            color: #7a8d9b;
            font-size: 0.82rem;
            line-height: 1.55;
        }

        .dashboard-stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 15px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: #fff;
            flex-shrink: 0;
            box-shadow: 0 10px 20px rgba(23, 48, 66, 0.14);
        }

        .tone-sky { background: linear-gradient(135deg, #2bbdce 0%, #1690c7 100%); }
        .tone-mint { background: linear-gradient(135deg, #1dbf9f 0%, #0f8b74 100%); }
        .tone-violet { background: linear-gradient(135deg, #6378ff 0%, #4457d7 100%); }
        .tone-amber { background: linear-gradient(135deg, #f2b84a 0%, #db8b1a 100%); }
        .tone-coral { background: linear-gradient(135deg, #ff8b73 0%, #ea5f68 100%); }
        .tone-indigo { background: linear-gradient(135deg, #5468ff 0%, #3044c9 100%); }
        .tone-slate { background: linear-gradient(135deg, #607d8b 0%, #415763 100%); }
        .tone-rose { background: linear-gradient(135deg, #ff6b8f 0%, #d94a7b 100%); }

        .dashboard-panel {
            height: 100%;
            border: 1px solid #e8eef3;
            border-radius: 24px;
            background: #fff;
            box-shadow: 0 14px 30px rgba(31, 53, 72, 0.05);
            overflow: hidden;
        }

        .dashboard-panel-header {
            padding: 1.1rem 1.2rem 0.95rem;
            border-bottom: 1px solid #eef3f6;
            background: linear-gradient(180deg, #ffffff 0%, #f9fbfd 100%);
        }

        .dashboard-panel-title {
            margin: 0;
            font-size: 0.97rem;
            font-weight: 700;
            color: #20303c;
        }

        .dashboard-panel-subtitle {
            margin: 0.32rem 0 0;
            color: #7a8d9b;
            font-size: 0.82rem;
            line-height: 1.6;
        }

        .dashboard-panel-body {
            padding: 0.95rem 1.1rem 1.1rem;
        }

        .dashboard-chart-wrap {
            position: relative;
            height: 300px;
        }

        .dashboard-list {
            display: grid;
            gap: 0.8rem;
        }

        .dashboard-list-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.9rem 0.95rem;
            border: 1px solid #edf2f7;
            border-radius: 18px;
            background: linear-gradient(180deg, #fcfdff 0%, #f9fbfd 100%);
        }

        .dashboard-list-title {
            font-weight: 600;
            color: #223644;
            font-size: 0.94rem;
        }

        .dashboard-list-subtitle {
            color: #7c8d9a;
            font-size: 0.81rem;
            margin-top: 0.18rem;
            line-height: 1.55;
        }

        .dashboard-list-value {
            text-align: right;
            font-weight: 600;
            color: #183244;
            white-space: nowrap;
            font-size: 0.93rem;
        }

        .dashboard-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 84px;
            padding: 0.36rem 0.65rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: capitalize;
        }

        .dashboard-badge.pending { background: rgba(242, 184, 74, 0.16); color: #b17210; }
        .dashboard-badge.completed { background: rgba(29, 191, 159, 0.16); color: #0f8b74; }
        .dashboard-badge.accept,
        .dashboard-badge.processing,
        .dashboard-badge.mark_as_shipped,
        .dashboard-badge.mark_as_delivered { background: rgba(84, 104, 255, 0.14); color: #4154d4; }
        .dashboard-badge.reject,
        .dashboard-badge.cancelled { background: rgba(234, 95, 104, 0.14); color: #c4475c; }

        @media (max-width: 1199.98px) {
            .dashboard-summary-strip {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 991.98px) {
            .dashboard-chart-wrap {
                height: 270px;
            }
        }

        @media (max-width: 767.98px) {
            .dashboard-summary-strip {
                grid-template-columns: 1fr;
            }

            .dashboard-stat-card {
                min-height: 148px;
            }

            .dashboard-list-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .dashboard-list-value {
                text-align: left;
            }
        }
    </style>
@endpush

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="dashboard-hero mb-4">
                <h1 class="dashboard-hero-title">Operations Dashboard</h1>
                <p class="dashboard-hero-text">
                    A live snapshot of users, services, marketplace activity, revenue, and seller performance so the team can track what needs attention at a glance.
                </p>
                <div class="dashboard-summary-strip">
                    <div class="dashboard-summary-chip">
                        <span class="dashboard-summary-label">Service Revenue</span>
                        <div class="dashboard-summary-value">SAR {{ number_format($financialSummary['service_revenue'], 2) }}</div>
                    </div>
                    <div class="dashboard-summary-chip">
                        <span class="dashboard-summary-label">Pending To System</span>
                        <div class="dashboard-summary-value">SAR {{ number_format($financialSummary['pending_to_system'], 2) }}</div>
                    </div>
                    <div class="dashboard-summary-chip">
                        <span class="dashboard-summary-label">Marketplace Revenue</span>
                        <div class="dashboard-summary-value">SAR {{ number_format($financialSummary['marketplace_revenue'], 2) }}</div>
                    </div>
                    <div class="dashboard-summary-chip">
                        <span class="dashboard-summary-label">Active Campaigns</span>
                        <div class="dashboard-summary-value">{{ number_format($financialSummary['active_campaigns']) }}</div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                @foreach ($cards as $card)
                    <div class="col-12 col-sm-6 col-xl-3 dashboard-stat-col">
                        <div class="dashboard-stat-card">
                            <div class="dashboard-stat-inner">
                                <div class="dashboard-stat-top">
                                    <div>
                                        <p class="dashboard-stat-label">{{ $card['label'] }}</p>
                                        <h3 class="dashboard-stat-value">{{ $card['value'] }}</h3>
                                    </div>
                                    <div class="dashboard-stat-icon {{ 'tone-' . $card['tone'] }}">
                                        <i class="bi {{ $card['icon'] }}"></i>
                                    </div>
                                </div>
                                <div class="dashboard-stat-meta">{{ $card['meta'] }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row g-4 mt-1">
                <div class="col-xl-6 d-flex">
                    <div class="dashboard-panel w-100">
                        <div class="dashboard-panel-header">
                            <h6 class="dashboard-panel-title">Growth Trends</h6>
                            <p class="dashboard-panel-subtitle">Monthly movement across users, providers, sellers, and requests.</p>
                        </div>
                        <div class="dashboard-panel-body">
                            <div class="dashboard-chart-wrap">
                                <canvas id="growthChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6 d-flex">
                    <div class="dashboard-panel w-100">
                        <div class="dashboard-panel-header">
                            <h6 class="dashboard-panel-title">Revenue Split</h6>
                            <p class="dashboard-panel-subtitle">Collected service revenue versus the amount still pending to the system.</p>
                        </div>
                        <div class="dashboard-panel-body">
                            <div class="dashboard-chart-wrap">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6 d-flex">
                    <div class="dashboard-panel w-100">
                        <div class="dashboard-panel-header">
                            <h6 class="dashboard-panel-title">Marketplace Order Status</h6>
                            <p class="dashboard-panel-subtitle">Current order flow across pending, completed, and rejected states.</p>
                        </div>
                        <div class="dashboard-panel-body">
                            <div class="dashboard-chart-wrap">
                                <canvas id="marketplaceStatusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6 d-flex">
                    <div class="dashboard-panel w-100">
                        <div class="dashboard-panel-header">
                            <h6 class="dashboard-panel-title">Recent Marketplace Orders</h6>
                            <p class="dashboard-panel-subtitle">Latest customer orders with order status and value snapshot.</p>
                        </div>
                        <div class="dashboard-panel-body">
                            <div class="dashboard-list">
                                @forelse ($recentMarketplaceOrders as $order)
                                    <div class="dashboard-list-item">
                                        <div>
                                            <div class="dashboard-list-title">#{{ $order->order_number }}</div>
                                            <div class="dashboard-list-subtitle">
                                                {{ $order->customer_name ?: 'Unknown customer' }} -
                                                {{ \Illuminate\Support\Carbon::parse($order->created_at)->format('d M Y, h:i A') }}
                                            </div>
                                        </div>
                                        <div class="dashboard-list-value">
                                            <div>SAR {{ number_format($order->total_amount, 2) }}</div>
                                            <span class="dashboard-badge {{ $order->status }}">{{ ucwords(str_replace('_', ' ', $order->status)) }}</span>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-muted">No marketplace orders found yet.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 d-flex">
                    <div class="dashboard-panel w-100">
                        <div class="dashboard-panel-header">
                            <h6 class="dashboard-panel-title">Top Seller Activity</h6>
                            <p class="dashboard-panel-subtitle">Sellers ranked by marketplace orders, with their product count alongside.</p>
                        </div>
                        <div class="dashboard-panel-body">
                            <div class="row g-3">
                                @forelse ($topSellers as $seller)
                                    <div class="col-lg-4 col-md-6 d-flex">
                                        <div class="dashboard-list-item w-100">
                                            <div>
                                                <div class="dashboard-list-title">{{ $seller->shop_title ?: $seller->name ?: 'Unnamed Seller' }}</div>
                                                <div class="dashboard-list-subtitle">{{ $seller->products_count }} products listed</div>
                                            </div>
                                            <div class="dashboard-list-value">
                                                <div>{{ $seller->orders_count }} orders</div>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="col-12 text-muted">No seller performance data available yet.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection

@push('scripts')
    <script>
        const dashboardCurrency = value => `SAR ${Number(value || 0).toLocaleString()}`;

        new Chart(document.getElementById('growthChart'), {
            type: 'line',
            data: {
                labels: @json($growthChart['labels']),
                datasets: [{
                    label: 'Users',
                    data: @json($growthChart['series']['users']),
                    borderColor: '#2bbdce',
                    backgroundColor: 'rgba(43, 189, 206, 0.12)',
                    tension: 0.35,
                    fill: false
                }, {
                    label: 'Providers',
                    data: @json($growthChart['series']['providers']),
                    borderColor: '#1dbf9f',
                    backgroundColor: 'rgba(29, 191, 159, 0.12)',
                    tension: 0.35,
                    fill: false
                }, {
                    label: 'Sellers',
                    data: @json($growthChart['series']['sellers']),
                    borderColor: '#6378ff',
                    backgroundColor: 'rgba(99, 120, 255, 0.12)',
                    tension: 0.35,
                    fill: false
                }, {
                    label: 'Requests',
                    data: @json($growthChart['series']['requests']),
                    borderColor: '#f2b84a',
                    backgroundColor: 'rgba(242, 184, 74, 0.12)',
                    tension: 0.35,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        new Chart(document.getElementById('revenueChart'), {
            type: 'doughnut',
            data: {
                labels: @json($serviceRevenueChart['labels']),
                datasets: [{
                    data: @json($serviceRevenueChart['values']),
                    backgroundColor: ['#2bbdce', '#ea5f68'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.label}: ${dashboardCurrency(context.raw)}`;
                            }
                        }
                    }
                }
            }
        });

        new Chart(document.getElementById('marketplaceStatusChart'), {
            type: 'bar',
            data: {
                labels: @json($marketplaceStatusChart['labels']),
                datasets: [{
                    label: 'Orders',
                    data: @json($marketplaceStatusChart['values']),
                    backgroundColor: ['#6378ff', '#1dbf9f', '#ff8b73'],
                    borderRadius: 12,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    </script>
@endpush
