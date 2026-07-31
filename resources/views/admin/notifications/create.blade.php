@extends('layouts.app')

@section('title', 'Broadcast Notification')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show text-white" role="alert">
                    <span class="alert-icon"><i class="bi bi-check-circle-fill"></i></span>
                    <span class="alert-text"><strong>Success!</strong> {{ session('success') }}</span>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show text-white" role="alert">
                    <span class="alert-icon"><i class="bi bi-exclamation-triangle-fill"></i></span>
                    <span class="alert-text"><strong>Error!</strong> {{ session('error') }}</span>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0" style="border-radius: 20px;">
                        <div class="card-header bg-transparent pb-0">
                            <div class="d-flex align-items-center justify-content-between">
                                <h6 class="mb-0 text-dark font-weight-bold" style="font-size: 1.1rem;">Broadcast Notification</h6>
                                <span class="badge bg-light text-secondary" style="border-radius: 8px;">FCM Push & Database Inbox</span>
                            </div>
                            <p class="text-muted text-xs mt-1">Send a push notification and database inbox message to all or segmented mobile app users instantly.</p>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('admin.notifications.store') }}" method="POST" class="admin-loader-form">
                                @csrf

                                <div class="mb-4">
                                    <label for="target_audience" class="form-label text-dark font-weight-semibold">Target Audience</label>
                                    <select name="target_audience" id="target_audience" class="form-select select2" required>
                                        <option value="all">All Registered Users</option>
                                        <option value="0">Customers / Users Only</option>
                                        <option value="1">Service Providers Only</option>
                                        <option value="2">Marketplace Sellers Only</option>
                                    </select>
                                    @error('target_audience')
                                        <small class="text-danger d-block mt-1">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="mb-4">
                                    <label for="title" class="form-label text-dark font-weight-semibold">Notification Title</label>
                                    <input type="text" name="title" id="title" class="form-control" placeholder="e.g. Exciting New Updates!" value="{{ old('title') }}" required>
                                    @error('title')
                                        <small class="text-danger d-block mt-1">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="mb-4">
                                    <label for="body" class="form-label text-dark font-weight-semibold">Message Body</label>
                                    <textarea name="body" id="body" class="form-control" rows="5" placeholder="Type your message description here..." required>{{ old('body') }}</textarea>
                                    @error('body')
                                        <small class="text-danger d-block mt-1">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-4">
                                    <button type="reset" class="btn btn-secondary border-radius-lg px-4">Reset Form</button>
                                    <button type="submit" class="btn btn-primary border-radius-lg px-4" style="background-color: #4F2396 !important; border-color: #4F2396 !important;">
                                        <i class="bi bi-send-fill me-2"></i> Send Notification
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
