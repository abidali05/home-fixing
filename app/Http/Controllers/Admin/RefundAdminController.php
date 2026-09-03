<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use Illuminate\Http\Request;

class RefundAdminController extends Controller
{
    /**
     * Admin Refund Requests List API / View
     */
    public function index(Request $request)
    {
        $status = $request->input('status');
        $type = $request->input('type');

        $query = Refund::with(['customer', 'bankAccount', 'order', 'marketplaceOrder'])->orderByDesc('id');

        $allCount = Refund::count();
        $serviceCount = Refund::whereNotNull('order_id')->count();
        $marketplaceCount = Refund::whereNotNull('marketplace_order_id')->count();

        if (!empty($status)) {
            $query->where('status', $status);
        }

        if ($type === 'service') {
            $query->whereNotNull('order_id');
        } elseif ($type === 'marketplace') {
            $query->whereNotNull('marketplace_order_id');
        }

        if ($request->wantsJson() || $request->is('api/*')) {
            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', 20);
            $paginated = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Admin refund requests fetched successfully.',
                'data' => [
                    'refunds' => $paginated->items(),
                    'summary' => [
                        'all_count' => $allCount,
                        'service_count' => $serviceCount,
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

        $refunds = $query->get();
        return view('admin.refunds.index', compact('refunds', 'type', 'allCount', 'serviceCount', 'marketplaceCount'));
    }

    /**
     * Admin Accept Refund Request
     */
    public function accept(Request $request, $id)
    {
        $refund = Refund::findOrFail($id);

        if (in_array($refund->status, ['refunded', 'rejected'])) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Completed or rejected refund requests cannot be processed again.'
                ], 422);
            }
            return redirect()->back()->with('error', 'Completed or rejected refund requests cannot be processed again.');
        }

        $refund->update([
            'status' => 'accepted',
        ]);

        if ($refund->order) {
            $refund->order->update(['refund_status' => 'accepted']);
        }

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'Refund request accepted successfully.',
                'data' => [
                    'id' => $refund->id,
                    'refund_no' => $refund->refund_no,
                    'status' => 'accepted',
                    'accepted_at' => $refund->updated_at ? $refund->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                ]
            ]);
        }

        return redirect()->back()->with('success', 'Refund request ' . $refund->refund_no . ' accepted.');
    }

    /**
     * Admin Complete Refund Request after Bank Transfer / Gateway Refund
     */
    public function complete(Request $request, $id)
    {
        $refund = Refund::findOrFail($id);

        if (in_array($refund->status, ['refunded', 'rejected'])) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Completed or rejected refund requests cannot be processed again.'
                ], 422);
            }
            return redirect()->back()->with('error', 'Completed or rejected refund requests cannot be processed again.');
        }

        $bankReference = $request->input('bank_reference') ?: $request->input('refund_reference');

        $refund->update([
            'status' => 'refunded',
            'bank_reference' => $bankReference ?: $refund->bank_reference,
            'refunded_at' => now()->setTimezone('Asia/Riyadh'),
            'admin_notes' => $bankReference ? "Bank Ref: {$bankReference}" : $refund->admin_notes,
        ]);

        if ($refund->order) {
            $refund->order->update(['refund_status' => 'refunded']);
        }

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'Refund request completed successfully.',
                'data' => [
                    'id' => $refund->id,
                    'refund_no' => $refund->refund_no,
                    'status' => 'refunded',
                    'refund_reference' => $bankReference,
                    'refunded_at' => $refund->refunded_at ? $refund->refunded_at->toIso8601String() : null,
                ]
            ]);
        }

        return redirect()->back()->with('success', 'Refund request ' . $refund->refund_no . ' marked as completed/refunded.');
    }

    /**
     * Admin Reject Refund Request with Reason
     */
    public function reject(Request $request, $id)
    {
        $refund = Refund::findOrFail($id);

        if (in_array($refund->status, ['refunded', 'rejected'])) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Completed or rejected refund requests cannot be processed again.'
                ], 422);
            }
            return redirect()->back()->with('error', 'Completed or rejected refund requests cannot be processed again.');
        }

        $reason = $request->input('reason') ?: ($request->input('failure_reason') ?: ($request->input('admin_notes') ?: 'The submitted refund request could not be processed.'));

        $refund->update([
            'status' => 'rejected',
            'failure_reason' => $reason,
            'admin_notes' => $reason,
            'failed_at' => now()->setTimezone('Asia/Riyadh'),
        ]);

        if ($refund->order) {
            $refund->order->update(['refund_status' => 'rejected']);
        }

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'Refund request rejected successfully.',
                'data' => [
                    'id' => $refund->id,
                    'refund_no' => $refund->refund_no,
                    'status' => 'rejected',
                    'reason' => $reason,
                    'rejected_at' => $refund->failed_at ? $refund->failed_at->toIso8601String() : null,
                ]
            ]);
        }

        return redirect()->back()->with('success', 'Refund request ' . $refund->refund_no . ' rejected.');
    }
}
