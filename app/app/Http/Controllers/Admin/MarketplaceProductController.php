<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\ServiceCategoryModel;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketplaceProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()
            ->with(['category', 'seller.marketplaceProfile']);

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($builder) use ($search) {
                $builder->where('product_name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhereHas('seller', function ($sellerQuery) use ($search) {
                        $sellerQuery->where('name', 'like', "%{$search}%")
                            ->orWhereHas('marketplaceProfile', function ($marketplaceQuery) use ($search) {
                                $marketplaceQuery->where('shop_title', 'like', "%{$search}%");
                            });
                    });
            });
        }

        if ($request->filled('seller_id')) {
            $query->where('user_id', $request->seller_id);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('stock_availability')) {
            if ($request->stock_availability === 'in_stock') {
                $query->where('total_stock', '>', 0);
            }

            if ($request->stock_availability === 'out_of_stock') {
                $query->where('total_stock', '<=', 0);
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $products = $query->latest()->paginate(15)->withQueryString();

        return view('admin.marketplace.products.index', [
            'products' => $products,
            'sellers' => User::query()->whereHas('marketplaceProfile')->orderBy('name')->get(),
            'categories' => ServiceCategoryModel::query()->orderBy('name')->get(),
            'statuses' => ['publish', 'unpublish', 'trash'],
        ]);
    }

    public function create()
    {
        return view('admin.marketplace.products.create', [
            'product' => new Product(),
            'sellers' => User::query()->whereHas('marketplaceProfile')->orderBy('name')->get(),
            'categories' => ServiceCategoryModel::query()->orderBy('name')->get(),
            'statuses' => ['publish', 'unpublish', 'trash'],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateProduct($request);

        DB::beginTransaction();

        try {
            $product = new Product();
            $this->fillProduct($product, $request, $validated);
            $product->save();

            DB::commit();

            return redirect()->route('marketplace.products.index')->with('success', 'Product created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to create product.')->withInput();
        }
    }

    public function show($id)
    {
        $product = Product::query()->with(['category', 'seller.marketplaceProfile', 'campaigns'])->findOrFail($id);

        return view('admin.marketplace.products.show', compact('product'));
    }

    public function edit($id)
    {
        $product = Product::findOrFail($id);

        return view('admin.marketplace.products.edit', [
            'product' => $product,
            'sellers' => User::query()->whereHas('marketplaceProfile')->orderBy('name')->get(),
            'categories' => ServiceCategoryModel::query()->orderBy('name')->get(),
            'statuses' => ['publish', 'unpublish', 'trash'],
        ]);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $validated = $this->validateProduct($request, $product->id);

        DB::beginTransaction();

        try {
            $this->fillProduct($product, $request, $validated);
            $product->save();

            DB::commit();

            return redirect()->route('marketplace.products.index')->with('success', 'Product updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to update product.')->withInput();
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:publish,unpublish,pending,trash',
        ]);

        $product->update([
            'status' => $validated['status'],
        ]);

        return back()->with('success', 'Product status updated successfully.');
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->update(['status' => 'trash']);

        return back()->with('success', 'Product moved to trash successfully.');
    }

    private function validateProduct(Request $request, ?int $productId = null): array
    {
        return $request->validate([
            'user_id' => 'required|exists:users,id',
            'banner_image' => $productId ? 'nullable|image|mimes:jpeg,png,jpg,gif|max:8192' : 'required|image|mimes:jpeg,png,jpg,gif|max:8192',
            'product_images' => 'nullable|array',
            'product_images.*' => 'image|mimes:jpeg,png,jpg,gif|max:8192',
            'category_id' => 'required|exists:categories,id',
            'status' => 'required|in:publish,unpublish,pending,trash',
            'product_name' => 'required|string|max:255',
            'product_description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|string|max:255',
            'discount_value' => 'nullable|numeric|min:0',
            'tax_status' => 'required|string|max:255',
            'installation_available' => 'nullable|boolean',
            'installation_price' => 'nullable|numeric|min:0',
            'installation_details' => 'nullable|string',
            'weight' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
            'total_stock' => 'required|integer|min:0',
            'limited_stock' => 'nullable|integer|min:0',
            'sku' => 'required|string|max:255',
        ]);
    }

    private function fillProduct(Product $product, Request $request, array $validated): void
    {
        if ($request->hasFile('banner_image')) {
            $bannerImage = $request->file('banner_image');
            $bannerPath = $bannerImage->store('products/banners', 'public');
            $product->banner_image = $bannerPath;
        }

        $existingImages = $this->parseProductImages($product->product_images);

        if ($request->hasFile('product_images')) {
            $uploadedImages = [];

            foreach ($request->file('product_images') as $image) {
                $uploadedImages[] = $image->store('products/images', 'public');
            }

            $existingImages = array_values(array_unique(array_merge($existingImages, $uploadedImages)));
        }

        $product->fill([
            'user_id' => $validated['user_id'],
            'category_id' => $validated['category_id'],
            'status' => $validated['status'],
            'product_name' => $validated['product_name'],
            'product_description' => $validated['product_description'],
            'price' => $validated['price'],
            'sale_price' => $validated['sale_price'] ?? null,
            'discount_type' => $validated['discount_type'] ?? null,
            'discount_value' => $validated['discount_value'] ?? null,
            'tax_status' => $validated['tax_status'],
            'installation_available' => (bool) ($validated['installation_available'] ?? false),
            'installation_price' => $validated['installation_price'] ?? null,
            'installation_details' => $validated['installation_details'] ?? null,
            'weight' => $validated['weight'] ?? null,
            'height' => $validated['height'] ?? null,
            'width' => $validated['width'] ?? null,
            'length' => $validated['length'] ?? null,
            'total_stock' => $validated['total_stock'],
            'limited_stock' => $validated['limited_stock'] ?? null,
            'sku' => $validated['sku'],
            'product_images' => !empty($existingImages) ? implode(',', $existingImages) : null,
        ]);
    }

    private function parseProductImages($images): array
    {
        if (is_array($images)) {
            return array_values(array_filter($images));
        }

        if (is_string($images) && $images !== '') {
            $decoded = json_decode($images, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values(array_filter($decoded));
            }

            return array_values(array_filter(explode(',', $images)));
        }

        return [];
    }
}
