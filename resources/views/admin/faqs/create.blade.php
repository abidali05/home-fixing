@extends('layouts.app')

@section('title', 'Create FAQ')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0">Create FAQ</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('faqs.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Question</label>
                            <input type="text" name="question" value="{{ old('question') }}"
                                class="form-control @error('question') is-invalid @enderror" required>
                            @error('question')
                                <span class="invalid-feedback d-block">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Answer</label>
                            <textarea name="answer" rows="4" class="form-control @error('answer') is-invalid @enderror" required>{{ old('answer') }}</textarea>
                            @error('answer')
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
                        <a href="{{ route('faqs.index') }}" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </main>
@endsection
