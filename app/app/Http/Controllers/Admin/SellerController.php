<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceOrderItem;
use App\Models\MarketplaceProfile;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SellerController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()
            ->with('marketplaceProfile')
            ->whereHas('marketplaceProfile');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhereHas('marketplaceProfile', function ($marketplaceQuery) use ($search) {
                        $marketplaceQuery->where('shop_title', 'like', "%{$search}%")
                            ->orWhere('tag_line', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . trim($request->name) . '%');
        }

        if ($request->filled('email')) {
            $query->where('email', 'like', '%' . trim($request->email) . '%');
        }

        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%' . trim($request->phone) . '%');
        }

        if ($request->filled('status')) {
            $query->where('marketplace_status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $sellers = $query->latest()->paginate(15)->withQueryString();

        return view('admin.sellers.index', [
            'sellers' => $sellers,
            'statuses' => ['active', 'inactive', 'suspended', 'banned'],
        ]);
    }

    public function show($id)
    {
        $seller = User::query()
            ->with('marketplaceProfile')
            ->whereHas('marketplaceProfile')
            ->findOrFail($id);

        $productCount = Product::query()->where('user_id', $seller->id)->count();
        $orderCount = MarketplaceOrderItem::query()
            ->where('shop_id', $seller->id)
            ->distinct('marketplace_order_id')
            ->count('marketplace_order_id');
        $totalSales = MarketplaceOrderItem::query()
            ->where('shop_id', $seller->id)
            ->whereHas('order', function ($query) {
                $query->where('status', 'completed');
            })
            ->sum('total_price');

        return view('admin.sellers.show', compact('seller', 'productCount', 'orderCount', 'totalSales'));
    }

    public function edit($id)
    {
        $seller = User::query()
            ->with('marketplaceProfile')
            ->whereHas('marketplaceProfile')
            ->findOrFail($id);

        return view('admin.sellers.edit', [
            'seller' => $seller,
            'statuses' => ['active', 'inactive', 'suspended', 'banned'],
            'shopStatuses' => ['on', 'off'],
        ]);
    }

    public function update(Request $request, $id)
    {
        $seller = User::query()
            ->with('marketplaceProfile')
            ->whereHas('marketplaceProfile')
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email,' . $seller->id,
            'phone' => ['required', 'regex:/^\+9665[0-9]{8}$/', 'unique:users,phone,' . $seller->id],
            'marketplace_status' => 'required|in:active,inactive,suspended,banned',
            'shop_title' => 'required|string|max:255',
            'tag_line' => 'nullable|string|max:255',
            'delivery_charges' => 'nullable|numeric|min:0',
            'bio' => 'nullable|string',
            'shop_status' => 'nullable|in:on,off',
            'document_type' => 'nullable|string|max:255',
            'document_number' => 'nullable|string|max:255',
            'shop_logo' => 'nullable|image|mimes:jpeg,png,jpg|max:8192',
            'shop_banner_image' => 'nullable|image|mimes:jpeg,png,jpg|max:8192',
        ]);

        DB::beginTransaction();

        try {
            $marketplaceProfile = $seller->marketplaceProfile ?: new MarketplaceProfile(['user_id' => $seller->id]);

            if ($request->hasFile('shop_logo')) {
                if (!empty($marketplaceProfile->shop_logo) && file_exists(public_path('uploads/shop_logos/' . $marketplaceProfile->shop_logo))) {
                    @unlink(public_path('uploads/shop_logos/' . $marketplaceProfile->shop_logo));
                }

                $file = $request->file('shop_logo');
                $marketplaceProfile->shop_logo = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/shop_logos/'), $marketplaceProfile->shop_logo);
            }

            if ($request->hasFile('shop_banner_image')) {
                if (!empty($marketplaceProfile->shop_banner_image) && file_exists(public_path('uploads/shop_banners/' . $marketplaceProfile->shop_banner_image))) {
                    @unlink(public_path('uploads/shop_banners/' . $marketplaceProfile->shop_banner_image));
                }

                $file = $request->file('shop_banner_image');
                $marketplaceProfile->shop_banner_image = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/shop_banners/'), $marketplaceProfile->shop_banner_image);
            }

            $seller->name = $validated['name'];
            $seller->email = $validated['email'] ?? null;
            $seller->phone = $validated['phone'];
            $seller->marketplace_status = $validated['marketplace_status'];

            if ((string) $seller->role === '2') {
                $seller->status = $validated['marketplace_status'];
            }

            $seller->save();

            $marketplaceProfile->fill([
                'shop_title' => $validated['shop_title'],
                'tag_line' => $validated['tag_line'] ?? null,
                'delivery_charges' => $validated['delivery_charges'] ?? null,
                'bio' => $validated['bio'] ?? null,
                'shop_status' => $validated['shop_status'] ?? $marketplaceProfile->shop_status,
                'document_type' => $validated['document_type'] ?? null,
                'document_number' => $validated['document_number'] ?? null,
            ]);
            $marketplaceProfile->user_id = $seller->id;
            $marketplaceProfile->save();

            DB::commit();

            return redirect()->route('sellers.index')->with('success', 'Seller updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Seller update failed: ' . $e->getMessage());

            return back()->with('error', 'Failed to update seller.')->withInput();
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $seller = User::query()
            ->whereHas('marketplaceProfile')
            ->findOrFail($id);

        $validated = $request->validate([
            'marketplace_status' => 'required|in:active,inactive,suspended,banned',
        ]);

        $seller->marketplace_status = $validated['marketplace_status'];

        if ((string) $seller->role === '2') {
            $seller->status = $validated['marketplace_status'];
        }

        $seller->save();

        return back()->with('success', 'Seller status updated successfully.');
    }
}
