@php
    $existingImages = [];

    if (is_array($product->product_images)) {
        $existingImages = array_values(array_filter($product->product_images));
    } elseif (is_string($product->product_images) && $product->product_images !== '') {
        $decodedImages = json_decode($product->product_images, true);
        $existingImages = json_last_error() === JSON_ERROR_NONE && is_array($decodedImages)
            ? array_values(array_filter($decodedImages))
            : array_values(array_filter(explode(',', $product->product_images)));
    }
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Seller</label>
        <select name="user_id" class="form-select select2" required>
            <option value="">Select Seller</option>
            @foreach ($sellers as $seller)
                <option value="{{ $seller->id }}" {{ (string) old('user_id', $product->user_id) === (string) $seller->id ? 'selected' : '' }}>
                    {{ $seller->marketplaceProfile->shop_title ?? $seller->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Category</label>
        <select name="category_id" class="form-select select2" required>
            <option value="">Select Category</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" {{ (string) old('category_id', $product->category_id) === (string) $category->id ? 'selected' : '' }}>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-8">
        <label class="form-label">Product Name</label>
        <input type="text" name="product_name" class="form-control" value="{{ old('product_name', $product->product_name) }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Status</label>
        <select name="status" class="form-select" required>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" {{ old('status', $product->status ?: 'publish') === $status ? 'selected' : '' }}>
                    {{ ucfirst($status) }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Banner Image {{ $product->exists ? '' : '*' }}</label>
        <input type="file" name="banner_image" class="form-control" {{ $product->exists ? '' : 'required' }}>
        @if ($product->banner_image)
            <div class="mt-2">
                <small class="text-muted d-block mb-2">Current banner image</small>
                <img src="{{ asset('storage/' . $product->banner_image) }}" alt="Current banner image"
                    style="width: 100%; max-width: 220px; height: 140px; object-fit: cover; border-radius: 14px; border: 1px solid #e4ebf1; background: #fff;">
            </div>
        @endif
    </div>
    <div class="col-md-4">
        <label class="form-label">Additional Images</label>
        <input type="file" name="product_images[]" class="form-control" multiple>
        @if (!empty($existingImages))
            <small class="text-muted d-block mt-2 mb-2">{{ count($existingImages) }} existing image(s)</small>
            <div class="d-flex flex-wrap gap-2">
                @foreach ($existingImages as $image)
                    <img src="{{ asset('storage/' . $image) }}" alt="Existing product image"
                        style="width: 78px; height: 78px; object-fit: cover; border-radius: 12px; border: 1px solid #e4ebf1; background: #fff;">
                @endforeach
            </div>
        @endif
    </div>
    <div class="col-md-4">
        <label class="form-label">SKU</label>
        <input type="text" name="sku" class="form-control" value="{{ old('sku', $product->sku) }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Price</label>
        <input type="number" step="0.01" min="0" name="price" class="form-control" value="{{ old('price', $product->price) }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Sale Price</label>
        <input type="number" step="0.01" min="0" name="sale_price" class="form-control" value="{{ old('sale_price', $product->sale_price) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Discount Type</label>
        <input type="text" name="discount_type" class="form-control" value="{{ old('discount_type', $product->discount_type) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Discount Value</label>
        <input type="number" step="0.01" min="0" name="discount_value" class="form-control" value="{{ old('discount_value', $product->discount_value) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Tax Status</label>
        <input type="text" name="tax_status" class="form-control" value="{{ old('tax_status', $product->tax_status) }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Installation Available</label>
        <select name="installation_available" class="form-select">
            <option value="0" {{ !old('installation_available', $product->installation_available) ? 'selected' : '' }}>No</option>
            <option value="1" {{ old('installation_available', $product->installation_available) ? 'selected' : '' }}>Yes</option>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Installation Price</label>
        <input type="number" step="0.01" min="0" name="installation_price" class="form-control" value="{{ old('installation_price', $product->installation_price) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Total Stock</label>
        <input type="number" min="0" name="total_stock" class="form-control" value="{{ old('total_stock', $product->total_stock) }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Limited Stock</label>
        <input type="number" min="0" name="limited_stock" class="form-control" value="{{ old('limited_stock', $product->limited_stock) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Weight</label>
        <input type="number" step="0.01" min="0" name="weight" class="form-control" value="{{ old('weight', $product->weight) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Height</label>
        <input type="number" step="0.01" min="0" name="height" class="form-control" value="{{ old('height', $product->height) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Width</label>
        <input type="number" step="0.01" min="0" name="width" class="form-control" value="{{ old('width', $product->width) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Length</label>
        <input type="number" step="0.01" min="0" name="length" class="form-control" value="{{ old('length', $product->length) }}">
    </div>
    <div class="col-12">
        <label class="form-label">Product Description</label>
        <textarea name="product_description" rows="4" class="form-control" required>{{ old('product_description', $product->product_description) }}</textarea>
    </div>
    <div class="col-12">
        <label class="form-label">Installation Details</label>
        <textarea name="installation_details" rows="3" class="form-control">{{ old('installation_details', $product->installation_details) }}</textarea>
    </div>
</div>
