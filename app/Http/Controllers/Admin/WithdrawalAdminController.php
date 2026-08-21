<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use Illuminate\Http\Request;

class WithdrawalAdminController extends Controller
{
    /**
     * Admin Withdrawal List API / View
     */
    public function index(Request $request)
    {
        $status = $request->input('status');
        $accountType = $request->input('account_type');

        $query = Withdrawal::with(['user', 'bankAccount'])->orderByDesc('id');

        $allCount = Withdrawal::count();
        $providerCount = Withdrawal::where('account_type', 'provider')->count();
        $marketplaceCount = Withdrawal::where('account_type', 'marketplace')->count();

        if (!empty($status)) {
            $query->where('status', $status);
        }

        if (!empty($accountType) && $accountType !== 'all') {
            $query->where('account_type', $accountType);
        }

        if ($request->wantsJson() || $request->is('api/*')) {
            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', 20);
            $paginated = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Admin withdrawals fetched successfully.',
                'data' => [
                    'withdrawals' => $paginated->items(),
                    'summary' => [
                        'all_count' => $allCount,
                        'provider_count' => $providerCount,
                        'marketplace_count' => $marketplaceCount,
                    ],
                    'pagination' => [
                        'current_page' => $paginated->currentPage(),
                        'per_page' => $paginated->perPage(),
                        'total' => $paginated->total(),
                        'last_page' => $paginated->lastPage(),
                    ]
                ]
            ]);
        }

        $withdrawals = $query->get();
        return view('admin.withdrawals.index', compact('withdrawals', 'accountType', 'allCount', 'providerCount', 'marketplaceCount'));
    }

    /**
     * Admin Accept Withdrawal Request
     */
    public function accept(Request $request, $id)
    {
        $withdrawal = Withdrawal::findOrFail($id);

        if (in_array($withdrawal->status, ['completed', 'paid', 'rejected'])) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Completed or rejected withdrawal requests cannot be processed again.'
                ], 422);
            }
            return redirect()->back()->with('error', 'Completed or rejected withdrawal requests cannot be processed again.');
        }

        $withdrawal->update([
            'status' => 'approved',
        ]);

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request approved successfully.',
                'data' => [
                    'id' => $withdrawal->id,
                    'status' => 'approved',
                    'approved_at' => $withdrawal->updated_at ? $withdrawal->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                ]
            ]);
        }

        return redirect()->back()->with('success', 'Withdrawal request WDR-' . str_pad($withdrawal->id, 6, '0', STR_PAD_LEFT) . ' approved.');
    }

    /**
     * Admin Complete Withdrawal Payout after Bank Transfer
     */
    public function complete(Request $request, $id)
    {
        $withdrawal = Withdrawal::findOrFail($id);

        if (in_array($withdrawal->status, ['completed', 'paid', 'rejected'])) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Completed or rejected withdrawal requests cannot be processed again.'
                ], 422);
            }
            return redirect()->back()->with('error', 'Completed or rejected withdrawal requests cannot be processed again.');
        }

        $bankReference = $request->input('bank_reference') ?: $request->input('reference_number');

        $withdrawal->update([
            'status' => 'completed',
            'bank_reference' => $bankReference ?: $withdrawal->bank_reference,
            'completed_at' => now()->setTimezone('Asia/Riyadh'),
            'admin_notes' => $bankReference ? "Bank Transfer Ref: {$bankReference}" : $withdrawal->admin_notes,
        ]);

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'Withdrawal payout marked as completed successfully.',
                'data' => [
                    'id' => $withdrawal->id,
                    'status' => 'completed',
                    'bank_reference' => $bankReference,
                    'completed_at' => $withdrawal->completed_at ? $withdrawal->completed_at->toIso8601String() : null,
                ]
            ]);
        }

        return redirect()->back()->with('success', 'Withdrawal WDR-' . str_pad($withdrawal->id, 6, '0', STR_PAD_LEFT) . ' marked as completed.');
    }

    /**
     * Admin Reject Withdrawal Request with Reason
     */
    public function reject(Request $request, $id)
    {
        $withdrawal = Withdrawal::findOrFail($id);

        if (in_array($withdrawal->status, ['completed', 'paid', 'rejected'])) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Completed or rejected withdrawal requests cannot be processed again.'
                ], 422);
            }
            return redirect()->back()->with('error', 'Completed or rejected withdrawal requests cannot be processed again.');
        }

        $reason = $request->input('reason') ?: ($request->input('failure_reason') ?: ($request->input('admin_notes') ?: 'Withdrawal request rejected by Admin.'));

        $withdrawal->update([
            'status' => 'rejected',
            'failure_reason' => $reason,
            'admin_notes' => $reason,
            'failed_at' => now()->setTimezone('Asia/Riyadh'),
        ]);

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request rejected successfully.',
                'data' => [
                    'id' => $withdrawal->id,
                    'status' => 'rejected',
                    'reason' => $reason,
                    'rejected_at' => $withdrawal->failed_at ? $withdrawal->failed_at->toIso8601String() : null,
                ]
            ]);
        }

        return redirect()->back()->with('success', 'Withdrawal WDR-' . str_pad($withdrawal->id, 6, '0', STR_PAD_LEFT) . ' rejected.');
    }
}
