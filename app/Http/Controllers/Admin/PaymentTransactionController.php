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

        $paymentsQuery = Payment::with(['user', 'provider', 'job', 'bid'])
            ->orderByDesc('id');

        // Optional status filter
        if ($request->filled('status')) {
            $paymentsQuery->where('status', $request->status);
        }

        $payments = $paymentsQuery->get();

        $capturedPayments = $payments->where('status', 'captured');
        $totalVolume = (float) $capturedPayments->sum('amount');
        $systemEarnings = $totalVolume * ($azhlPercentage / 100.00);
        $providerPayouts = $totalVolume - $systemEarnings;

        $stats = [
            'total_volume' => $totalVolume,
            'system_earnings' => $systemEarnings,
            'provider_payouts' => $providerPayouts,
            'azhl_percentage' => $azhlPercentage,
            'total_transactions' => $payments->count(),
            'captured_count' => $capturedPayments->count(),
            'failed_count' => $payments->whereIn('status', ['failed', 'declined', 'cancelled'])->count(),
        ];

        return view('admin.payments.index', compact('payments', 'stats', 'settings'));
    }
}
