<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use Illuminate\Http\Request;

class WithdrawalAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = Withdrawal::with(['user', 'bankAccount'])->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('account_type')) {
            $query->where('account_type', $request->account_type);
        }

        $withdrawals = $query->get();

        return view('admin.withdrawals.index', compact('withdrawals'));
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected,paid,completed',
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $withdrawal = Withdrawal::findOrFail($id);
        $newStatus = $request->status;

        // Map status to doc specs ('completed' or 'rejected')
        if ($newStatus === 'approved' || $newStatus === 'paid') {
            $newStatus = 'completed';
        }

        $withdrawal->update([
            'status' => $newStatus,
            'admin_notes' => $request->admin_notes,
        ]);

        return redirect()->back()->with('success', "Withdrawal request #{$withdrawal->id} updated to " . ucfirst($newStatus));
    }
}
