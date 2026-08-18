<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketplaceOrderController extends Controller
{
    private array $orderStatuses = [
        'pending',
        'accept',
        'reject',
        'processing',
        'mark_as_shipped',
        'mark_as_delivered',
        'completed',
    ];

    private array $paymentStatuses = [
        'pending',
        'paid',
        'failed',
        'refunded',
    ];

    public function index(Request $request)
    {
        $query = MarketplaceOrder::query()
            ->with(['customer', 'items.shop.marketplaceProfile']);

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($builder) use ($search) {
                $builder->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items.shop.marketplaceProfile', function ($sellerQuery) use ($search) {
                        $sellerQuery->where('shop_title', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items.shop', function ($sellerQuery) use ($search) {
                        $sellerQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('seller_id')) {
            $query->whereHas('items', function ($itemQuery) use ($request) {
                $itemQuery->where('shop_id', $request->seller_id);
            });
        }

        if ($request->filled('customer_id')) {
            $query->where('user_id', $request->customer_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query->latest()->paginate(15)->withQueryString();
        $sellers = User::query()->whereHas('marketplaceProfile')->orderBy('name')->get();
        $customers = User::query()->where('role', '0')->orderBy('name')->get();

        return view('admin.marketplace.orders.index', [
            'orders' => $orders,
            'sellers' => $sellers,
            'customers' => $customers,
            'orderStatuses' => $this->orderStatuses,
            'paymentStatuses' => $this->paymentStatuses,
        ]);
    }

    public function show($id)
    {
        $order = MarketplaceOrder::query()
            ->with(['customer', 'items.product.category', 'items.shop.marketplaceProfile'])
            ->findOrFail($id);

        return view('admin.marketplace.orders.show', [
            'order' => $order,
            'orderStatuses' => $this->orderStatuses,
            'paymentStatuses' => $this->paymentStatuses,
        ]);
    }

    public function update(Request $request, $id)
    {
        $order = MarketplaceOrder::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', $this->orderStatuses),
            'payment_status' => 'required|in:' . implode(',', $this->paymentStatuses),
            'notes' => 'nullable|string',
            'delivery_response_reason' => 'nullable|string',
        ]);

        $order->update($validated);

        if ($request->boolean('redirect_back')) {
            return back()->with('success', 'Marketplace order updated successfully.');
        }

        return redirect()->route('marketplace.orders.show', $order->id)
            ->with('success', 'Marketplace order updated successfully.');
    }
}
