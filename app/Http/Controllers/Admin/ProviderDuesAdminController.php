<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendFcmTokenNotificationJob;
use App\Models\Orders;
use App\Models\ProviderCommissionRepayment;
use App\Models\ProviderDuesLedger;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProviderDuesAdminController extends Controller
{
    /**
     * Admin view of all providers and their payment due status
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status'); // all, blocked, has_due, clear

        $query = User::query()
            ->where('role', 1) // Providers only
            ->with(['providerProfile']);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('user_code', 'like', "%{$search}%");
            });
        }

        if ($status === 'blocked') {
            $query->whereRaw('payment_due >= max_due_threshold AND payment_due > 0');
        } elseif ($status === 'has_due') {
            $query->where('payment_due', '>', 0);
        } elseif ($status === 'clear') {
            $query->where('payment_due', '<=', 0);
        }

        // Stats summary
        $totalProviders = User::where('role', 1)->count();
        $totalDuesAmount = (float) User::where('role', 1)->sum('payment_due');
        $providersWithDues = User::where('role', 1)->where('payment_due', '>', 0)->count();
        $blockedProviders = User::where('role', 1)->whereRaw('payment_due >= max_due_threshold AND payment_due > 0')->count();

        $providers = $query->orderByDesc('payment_due')->paginate(20)->appends($request->all());

        return view('admin.provider_dues.index', compact(
            'providers',
            'search',
            'status',
            'totalProviders',
            'totalDuesAmount',
            'providersWithDues',
            'blockedProviders'
        ));
    }

    /**
     * Show specific provider ledger history
     */
    public function show($id)
    {
        $provider = User::where('role', 1)->with('providerProfile')->findOrFail($id);

        $ledgerEntries = ProviderDuesLedger::where('provider_id', $id)
            ->with(['order.user'])
            ->latest()
            ->paginate(25);

        // All Cash Orders for this provider
        $cashOrders = Orders::where('provider_id', $id)
            ->where('payment_method', 'cash')
            ->with('user')
            ->latest()
            ->take(10)
            ->get();

        return view('admin.provider_dues.show', compact('provider', 'ledgerEntries', 'cashOrders'));
    }

    /**
     * Admin manual adjustment of provider dues (e.g. if provider paid cash to office directly)
     */
    public function adjustDues(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|gt:0',
            'action' => 'required|in:credit_manual_payment,debit_manual_charge,clear_all',
            'notes' => 'nullable|string|max:255',
        ]);

        $provider = User::where('role', 1)->findOrFail($id);

        DB::transaction(function () use ($provider, $request) {
            $provider = User::where('id', $provider->id)->lockForUpdate()->first();
            $prevDue = (float) ($provider->payment_due ?? 0.0);
            $amount = (float) $request->amount;
            $notes = $request->input('notes') ?: 'Manual adjustment by Admin';

            if ($request->action === 'clear_all') {
                $newDue = 0.00;
                $adjAmount = $prevDue;
                $type = 'credit_manual_adjustment';
            } elseif ($request->action === 'credit_manual_payment') {
                $adjAmount = min($prevDue, $amount);
                $newDue = max(0.00, round($prevDue - $amount, 2));
                $type = 'credit_manual_adjustment';
            } else { // debit_manual_charge
                $adjAmount = $amount;
                $newDue = round($prevDue + $amount, 2);
                $type = 'debit_cash_order';
            }

            $provider->payment_due = $newDue;
            $threshold = (float) ($provider->max_due_threshold ?? 25.00);
            $provider->is_due_blocked = ($newDue >= $threshold) ? 1 : 0;
            $provider->save();

            ProviderDuesLedger::create([
                'provider_id' => $provider->id,
                'order_id' => null,
                'type' => $type,
                'amount' => $adjAmount,
                'previous_due' => $prevDue,
                'current_due' => $newDue,
                'notes' => "[Admin Adjustment] " . $notes,
            ]);

            if (in_array($request->action, ['clear_all', 'credit_manual_payment'])) {
                $adminId = auth()->id() ?? auth('admin')->id();
                ProviderCommissionRepayment::create([
                    'provider_id' => $provider->id,
                    'transaction_reference' => 'ADMIN-ADJ-' . $provider->id . '-' . time(),
                    'amount_paid' => $adjAmount,
                    'payment_method' => 'admin_override',
                    'status' => 'verified',
                    'verified_at' => now(),
                    'verified_by_admin_id' => $adminId,
                    'gateway_response' => [
                        'action' => $request->action,
                        'notes' => $notes,
                        'admin_id' => $adminId,
                    ],
                ]);

                if (!empty($provider->fcm_token)) {
                    try {
                        dispatch(new SendFcmTokenNotificationJob([$provider->fcm_token], [
                            'title' => 'تم تسوية مستحقاتك من الإدارة | Dues Cleared by Admin',
                            'body' => "تم تسوية عمولة أزهل بقيمة {$adjAmount} ر.س يدوياً وإلغاء أي حظر. الرصيد الحالي: {$newDue} ر.س.",
                            'data' => [
                                'type' => 'admin_dues_clearance',
                                'amount_cleared' => (string) $adjAmount,
                                'new_due' => (string) $newDue,
                                'is_due_blocked' => (string) $provider->is_due_blocked,
                            ],
                        ]));
                    } catch (\Throwable $e) {
                        Log::warning("ProviderDuesAdminController FCM error: " . $e->getMessage());
                    }
                }
            }
        });

        return redirect()->back()->with('success', 'Provider dues adjusted successfully.');
    }

    /**
     * Endpoint 5: Admin Manual Override via API
     * POST /api/admin/providers/{provider_id}/clear-dues
     */
    public function clearDuesApi(Request $request, $id): JsonResponse
    {
        $request->validate([
            'amount_received' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|in:bank_transfer,office_cash,admin_override',
            'bank_reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:255',
        ]);

        try {
            $provider = User::where('role', 1)->findOrFail($id);

            return DB::transaction(function () use ($provider, $request) {
                $provider = User::where('id', $provider->id)->lockForUpdate()->first();
                $prevDue = (float) ($provider->payment_due ?? 0.0);
                $amountReceived = $request->filled('amount_received') ? (float) $request->amount_received : $prevDue;

                if ($amountReceived <= 0) {
                    $amountReceived = $prevDue;
                }

                $newDue = max(0.00, round($prevDue - $amountReceived, 2));
                $provider->payment_due = $newDue;
                $threshold = (float) ($provider->max_due_threshold ?? 25.00);
                if ($newDue < $threshold) {
                    $provider->is_due_blocked = 0;
                }
                $provider->save();

                $adminId = auth()->id() ?? auth('admin')->id() ?? auth('sanctum')->id();
                $bankRef = $request->input('bank_reference') ?: ('TXN_ADMIN_' . time());
                $paymentMethod = $request->input('payment_method') ?: 'bank_transfer';
                $notes = $request->input('notes') ?: 'Manual override clearance by Admin';

                // Record repayment
                $repayment = ProviderCommissionRepayment::create([
                    'provider_id' => $provider->id,
                    'transaction_reference' => $bankRef,
                    'amount_paid' => $amountReceived,
                    'payment_method' => $paymentMethod === 'bank_transfer' ? 'bank_transfer' : 'admin_override',
                    'status' => 'verified',
                    'verified_at' => now(),
                    'verified_by_admin_id' => $adminId,
                    'gateway_response' => [
                        'bank_reference' => $bankRef,
                        'notes' => $notes,
                        'admin_id' => $adminId,
                        'payload' => $request->all(),
                    ],
                ]);

                // Record ledger
                ProviderDuesLedger::create([
                    'provider_id' => $provider->id,
                    'order_id' => null,
                    'type' => 'credit_manual_adjustment',
                    'amount' => $amountReceived,
                    'previous_due' => $prevDue,
                    'current_due' => $newDue,
                    'notes' => "[Admin Manual Override] " . $notes . " (Ref: {$bankRef})",
                ]);

                // FCM push alert
                if (!empty($provider->fcm_token)) {
                    try {
                        dispatch(new SendFcmTokenNotificationJob([$provider->fcm_token], [
                            'title' => 'تم تسوية مستحقاتك من الإدارة | Dues Cleared by Admin',
                            'body' => "تم تسوية عمولة أزهل بقيمة {$amountReceived} ر.س يدوياً وإلغاء أي حظر. الرصيد الحالي: {$newDue} ر.س.",
                            'data' => [
                                'type' => 'admin_dues_clearance',
                                'amount_cleared' => (string) $amountReceived,
                                'new_due' => (string) $newDue,
                                'is_due_blocked' => (string) ($provider->is_due_blocked ?? 0),
                            ],
                        ]));
                    } catch (\Throwable $e) {
                        Log::warning("clearDuesApi FCM error: " . $e->getMessage());
                    }
                }

                return response()->json([
                    'status' => true,
                    'code' => 200,
                    'message' => 'Provider dues cleared successfully.',
                    'data' => [
                        'provider_id' => (int) $provider->id,
                        'amount_cleared' => $amountReceived,
                        'previous_due' => $prevDue,
                        'new_due' => $newDue,
                        'is_due_blocked' => (int) ($provider->is_due_blocked ?? 0),
                        'is_blocked' => (bool) ($provider->is_due_blocked == 1 || $newDue >= $threshold),
                        'repayment_id' => $repayment->id,
                    ],
                ], 200);
            });
        } catch (\Throwable $e) {
            Log::error('ProviderDuesAdminController clearDuesApi error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to clear provider dues: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Admin can update provider's max allowed threshold (e.g. increase limit for trusted provider)
     */
    public function updateThreshold(Request $request, $id)
    {
        $request->validate([
            'max_due_threshold' => 'required|numeric|min:0',
        ]);

        $provider = User::where('role', 1)->findOrFail($id);
        $provider->max_due_threshold = (float) $request->max_due_threshold;
        $provider->save();

        return redirect()->back()->with('success', "Provider threshold limit updated to {$provider->max_due_threshold} SAR.");
    }
}
