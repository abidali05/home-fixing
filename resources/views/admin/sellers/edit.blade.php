@extends('layouts.app')

@section('title', 'Edit Seller')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Edit Seller</h6>
                            <a href="{{ route('sellers.index') }}" class="btn btn-sm btn-secondary">Back</a>
                        </div>
                        <div class="card-body">
                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @php
                                $profile = $seller->marketplaceProfile;
                            @endphp

                            <form action="{{ route('sellers.update', $seller->id) }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="row g-3">
                                     <div class="col-md-6">
                                         <label class="form-label">Name</label>
                                         <input type="text" name="name" class="form-control" value="{{ old('name', $seller->name) }}" required>
                                     </div>
                                     <div class="col-md-6">
                                         <label class="form-label">Phone</label>
                                         <input type="text" name="phone" class="form-control" value="{{ old('phone', $seller->phone) }}" required>
                                     </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Seller Status</label>
                                        <select name="marketplace_status" class="form-select">
                                            @foreach ($statuses as $status)
                                                <option value="{{ $status }}" {{ old('marketplace_status', $seller->marketplace_status) === $status ? 'selected' : '' }}>
                                                    {{ ucfirst($status) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Shop Title</label>
                                        <input type="text" name="shop_title" class="form-control" value="{{ old('shop_title', $profile?->shop_title) }}" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Shop Tag Line</label>
                                        <input type="text" name="tag_line" class="form-control" value="{{ old('tag_line', $profile?->tag_line) }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Delivery Charges</label>
                                        <input type="number" step="0.01" min="0" name="delivery_charges" class="form-control"
                                            value="{{ old('delivery_charges', $profile?->delivery_charges) }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Shop Status</label>
                                        <select name="shop_status" class="form-select">
                                            @foreach ($shopStatuses as $shopStatus)
                                                <option value="{{ $shopStatus }}" {{ old('shop_status', $profile?->shop_status) === $shopStatus ? 'selected' : '' }}>
                                                    {{ ucfirst($shopStatus) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Document Type</label>
                                        <input type="text" name="document_type" class="form-control"
                                            value="{{ old('document_type', $profile?->document_type) }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Document Number</label>
                                        <input type="text" name="document_number" class="form-control"
                                            value="{{ old('document_number', $profile?->document_number) }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Shop Logo</label>
                                        <input type="file" name="shop_logo" class="form-control">
                                        @if (!empty($profile?->shop_logo))
                                            <small class="text-muted d-block mt-1">Current: {{ $profile->shop_logo }}</small>
                                        @endif
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Shop Banner</label>
                                        <input type="file" name="shop_banner_image" class="form-control">
                                        @if (!empty($profile?->shop_banner_image))
                                            <small class="text-muted d-block mt-1">Current: {{ $profile->shop_banner_image }}</small>
                                        @endif
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Shop Bio</label>
                                        <textarea name="bio" rows="4" class="form-control">{{ old('bio', $profile?->bio) }}</textarea>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end mt-4">
                                    <button type="submit" class="btn btn-primary">Update Seller</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
