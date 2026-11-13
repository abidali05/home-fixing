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

        $marketplaceCommissionPct = (float) ($settings->marketplace_commission_percentage ?? 10.00);

        $capturedPayments = $allPayments->where('status', 'captured');
        $capturedServices = $servicePayments->where('status', 'captured');
        $capturedMarketplace = $marketplacePayments->where('status', 'captured');

        $serviceVolume = (float) $capturedServices->sum('amount');
        $marketplaceVolume = (float) $capturedMarketplace->sum('amount');
        $totalVolume = $serviceVolume + $marketplaceVolume;

        $serviceEarnings = $serviceVolume * ($azhlPercentage / 100);
        $marketplaceEarnings = $marketplaceVolume * ($marketplaceCommissionPct / 100);
        $systemEarnings = $serviceEarnings + $marketplaceEarnings;
        $providerPayouts = max(0, $totalVolume - $systemEarnings);

        $stats = [
            'total_volume' => $totalVolume,
            'system_earnings' => $systemEarnings,
            'provider_payouts' => $providerPayouts,
            'azhl_percentage' => $azhlPercentage,
            'marketplace_commission_percentage' => $marketplaceCommissionPct,
            'azhl_fee' => (float) ($settings->azhl_fee ?? 5.00),
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
