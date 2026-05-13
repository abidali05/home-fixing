@extends('layouts.app')

@section('title', 'Create Support Item')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0">Create Support Item</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('support_items.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" value="{{ old('title') }}"
                                class="form-control @error('title') is-invalid @enderror" required>
                            @error('title')
                                <span class="invalid-feedback d-block">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Value</label>
                            <textarea name="value" rows="3" class="form-control @error('value') is-invalid @enderror" required>{{ old('value') }}</textarea>
                            @error('value')
                                <span class="invalid-feedback d-block">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select name="type" class="form-select @error('type') is-invalid @enderror">
                                <option value="">Select type</option>
                                <option value="email" {{ old('type') === 'email' ? 'selected' : '' }}>Email</option>
                                <option value="phone" {{ old('type') === 'phone' ? 'selected' : '' }}>Phone</option>
                                <option value="whatsapp" {{ old('type') === 'whatsapp' ? 'selected' : '' }}>WhatsApp
                                </option>
                                <option value="address" {{ old('type') === 'address' ? 'selected' : '' }}>Address
                                </option>
                                <option value="link" {{ old('type') === 'link' ? 'selected' : '' }}>Link</option>
                            </select>
                            @error('type')
                                <span class="invalid-feedback d-block">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Icon Image (optional)</label>
                            <input type="file" name="icon"
                                class="form-control @error('icon') is-invalid @enderror" accept="image/*">
                            @error('icon')
                                <span class="invalid-feedback d-block">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Sort Order</label>
                            <input type="number" min="0" name="sort_order" value="{{ old('sort_order', 0) }}"
                                class="form-control @error('sort_order') is-invalid @enderror">
                            @error('sort_order')
                                <span class="invalid-feedback d-block">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive"
                                {{ old('is_active', 1) ? 'checked' : '' }}>
                            <label class="form-check-label" for="isActive">Active</label>
                        </div>

                        <button type="submit" class="btn btn-primary">Save</button>
                        <a href="{{ route('support_items.index') }}" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </main>
@endsection
