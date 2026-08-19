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
        $azhlFee = (float) ($settings->azhl_fee ?? 5.00);

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
        $systemEarnings = $capturedPayments->count() * $azhlFee;
        $providerPayouts = max(0, $totalVolume - $systemEarnings);

        // Marketplace specific stats
        $capturedMarketplace = $marketplacePayments->where('status', 'captured');
        $marketplaceVolume = (float) $capturedMarketplace->sum('amount');
        $marketplaceEarnings = $capturedMarketplace->count() * $azhlFee;

        $stats = [
            'total_volume' => $totalVolume,
            'system_earnings' => $systemEarnings,
            'provider_payouts' => $providerPayouts,
            'azhl_fee' => $azhlFee,
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
