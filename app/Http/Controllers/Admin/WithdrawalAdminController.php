<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use Illuminate\Http\Request;

class WithdrawalAdminController extends Controller
{
    /**
     * Admin Withdrawal List API / View
     * GET /api/v1/admin/withdrawals?status=requested&page=1
     */
    public function index(Request $request)
    {
        $status = $request->input('status');
        $accountType = $request->input('account_type');

        $query = Withdrawal::with(['user', 'bankAccount'])->orderByDesc('id');

        if (!empty($status)) {
            $query->where('status', $status);
        }

        if (!empty($accountType)) {
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
        return view('admin.withdrawals.index', compact('withdrawals'));
    }

    /**
     * Admin Accept Withdrawal Request
     * PATCH /api/v1/admin/withdrawals/{id}/accept
     */
    public function accept($id)
    {
        $withdrawal = Withdrawal::findOrFail($id);

        if (in_array($withdrawal->status, ['completed', 'rejected'])) {
            return response()->json([
                'success' => false,
                'message' => 'Completed or rejected requests cannot be processed again.'
            ], 422);
        }

        $withdrawal->update([
            'status' => 'accepted',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal request accepted successfully.',
            'data' => [
                'id' => $withdrawal->id,
                'withdrawal_no' => 'WDR-' . str_pad($withdrawal->id, 6, '0', STR_PAD_LEFT),
                'status' => 'accepted',
                'accepted_at' => $withdrawal->updated_at ? $withdrawal->updated_at->toIso8601String() : null,
            ]
        ]);
    }

    /**
     * Admin Complete Withdrawal Request after Bank Transfer
     * PATCH /api/v1/admin/withdrawals/{id}/complete
     */
    public function complete(Request $request, $id)
    {
        $withdrawal = Withdrawal::findOrFail($id);

        if (in_array($withdrawal->status, ['completed', 'rejected'])) {
            return response()->json([
                'success' => false,
                'message' => 'Completed or rejected requests cannot be processed again.'
            ], 422);
        }

        $bankReference = $request->input('bank_reference');

        $withdrawal->update([
            'status' => 'completed',
            'admin_notes' => $bankReference ? "Bank Ref: {$bankReference}" : $withdrawal->admin_notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal request completed successfully.',
            'data' => [
                'id' => $withdrawal->id,
                'withdrawal_no' => 'WDR-' . str_pad($withdrawal->id, 6, '0', STR_PAD_LEFT),
                'status' => 'completed',
                'bank_reference' => $bankReference,
                'completed_at' => $withdrawal->updated_at ? $withdrawal->updated_at->toIso8601String() : null,
            ]
        ]);
    }

    /**
     * Admin Reject Withdrawal Request with Reason
     * PATCH /api/v1/admin/withdrawals/{id}/reject
     */
    public function reject(Request $request, $id)
    {
        $withdrawal = Withdrawal::findOrFail($id);

        if (in_array($withdrawal->status, ['completed', 'rejected'])) {
            return response()->json([
                'success' => false,
                'message' => 'Completed or rejected requests cannot be processed again.'
            ], 422);
        }

        $reason = $request->input('reason', 'The submitted request could not be processed.');

        $withdrawal->update([
            'status' => 'rejected',
            'admin_notes' => $reason,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal request rejected successfully.',
            'data' => [
                'id' => $withdrawal->id,
                'withdrawal_no' => 'WDR-' . str_pad($withdrawal->id, 6, '0', STR_PAD_LEFT),
                'status' => 'rejected',
                'reason' => $reason,
                'rejected_at' => $withdrawal->updated_at ? $withdrawal->updated_at->toIso8601String() : null,
            ]
        ]);
    }
}
