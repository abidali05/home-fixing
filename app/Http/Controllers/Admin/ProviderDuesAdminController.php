<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Orders;
use App\Models\ProviderDuesLedger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        });

        return redirect()->back()->with('success', 'Provider dues adjusted successfully.');
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
