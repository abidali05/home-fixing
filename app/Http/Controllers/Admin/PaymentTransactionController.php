<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\SystemSettingModel;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentTransactionController extends Controller
{
    public function index(Request $request)
    {
        $settings = SystemSettingModel::first();
        $azhlPercentage = (float) ($settings->azhl_percentage ?? 10.00);

        $servicePayments = Payment::with(['user', 'provider', 'job', 'bid'])
            ->whereNull('marketplace_order_id')
            ->orderByDesc('id')
            ->get();

        $marketplacePayments = Payment::with(['user', 'marketplaceOrder'])
            ->whereNotNull('marketplace_order_id')
            ->orderByDesc('id')
            ->get();

        $allPayments = Payment::with(['user', 'provider', 'job', 'bid', 'marketplaceOrder'])
            ->orderByDesc('id')
            ->get();

        $capturedPayments = $allPayments->where('status', 'captured');
        $totalVolume = (float) $capturedPayments->sum('amount');
        $systemEarnings = $totalVolume * ($azhlPercentage / 100.00);
        $providerPayouts = $totalVolume - $systemEarnings;

        // Marketplace specific stats
        $capturedMarketplace = $marketplacePayments->where('status', 'captured');
        $marketplaceVolume = (float) $capturedMarketplace->sum('amount');
        $marketplaceEarnings = $marketplaceVolume * ($azhlPercentage / 100.00);

        $stats = [
            'total_volume' => $totalVolume,
            'system_earnings' => $systemEarnings,
            'provider_payouts' => $providerPayouts,
            'azhl_percentage' => $azhlPercentage,
            'total_transactions' => $allPayments->count(),
            'captured_count' => $capturedPayments->count(),
            'failed_count' => $allPayments->whereIn('status', ['failed', 'declined', 'cancelled'])->count(),
            'marketplace_volume' => $marketplaceVolume,
            'marketplace_earnings' => $marketplaceEarnings,
            'marketplace_count' => $marketplacePayments->count(),
        ];

        return view('admin.payments.index', compact('servicePayments', 'marketplacePayments', 'allPayments', 'stats', 'settings'));
    }
}
