<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label">Title</label>
        <input type="text" name="title" class="form-control" value="{{ old('title', $campaign->title) }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Status</label>
        <select name="status" class="form-select" required>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" {{ old('status', $campaign->status ?: 'inactive') === $status ? 'selected' : '' }}>
                    {{ ucfirst($status) }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Subtitle</label>
        <input type="text" name="subtitle" class="form-control" value="{{ old('subtitle', $campaign->subtitle) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Campaign Image {{ $campaign->exists ? '' : '*' }}</label>
        <input type="file" name="campaign_image" class="form-control" {{ $campaign->exists ? '' : 'required' }}>
        @if ($campaign->campaign_image)
            <div class="mt-2">
                <small class="text-muted d-block mb-2">Current campaign image</small>
                <img src="{{ asset('storage/' . $campaign->campaign_image) }}" alt="Current campaign image"
                    style="width: 100%; max-width: 240px; height: 150px; object-fit: cover; border-radius: 14px; border: 1px solid #e4ebf1; background: #fff;">
            </div>
        @endif
    </div>
    <div class="col-md-6">
        <label class="form-label">Start Date</label>
        <input type="date" name="start_date" class="form-control"
            value="{{ old('start_date', optional($campaign->start_date)->format('Y-m-d')) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">End Date</label>
        <input type="date" name="end_date" class="form-control"
            value="{{ old('end_date', optional($campaign->end_date)->format('Y-m-d')) }}" required>
    </div>
    <div class="col-12">
        <label class="form-label">Linked Product</label>
        <select name="product_id" class="form-select select2" required>
            <option value="">Select Product</option>
            @foreach ($products as $product)
                <option value="{{ $product->id }}" {{ (string) old('product_id', $campaign->product_id) === (string) $product->id ? 'selected' : '' }}>
                    {{ $product->product_name }} - {{ $product->seller?->marketplaceProfile?->shop_title ?: $product->seller?->name ?: 'Unknown Seller' }}
                </option>
            @endforeach
        </select>
    </div>
</div>
