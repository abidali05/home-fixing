@extends('layouts.app')

@section('title', 'Order Details')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-lg-12">

                    {{-- Order Details --}}
                    <div class="card shadow-sm mb-4">
                        <div class="card-header text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Order Details</h5>
                            <a href="{{ route('orders.index') }}" class="btn btn-light btn-sm">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <strong>User:</strong> {{ optional($order->user)->name ?? '-' }}
                                </div>
                                <div class="col-md-6">
                                    <strong>Provider:</strong> {{ optional($order->provider)->name ?? '-' }}
                                </div>
                                <div class="col-md-6">
                                    <strong>Order Date:</strong> {{ \Carbon\Carbon::parse($order->created_at)->format('d M, Y h:i A') }}
                                </div>
                              
                                <div class="col-md-6">
                                    <strong>Price:</strong> SAR {{ number_format($order->price, 2) }}
                                </div>
                                <div class="col-md-6">
                                    <strong>Source:</strong> {{ ucfirst($order->source) }}
                                </div>
                                <div class="col-md-6">
                                    <strong>Status:</strong>
                                    <span
                                        class="badge bg-{{ $order->status == 'pending' ? 'warning' : ($order->status == 'completed' ? 'success' : 'secondary') }}">
                                        {{ ucfirst($order->status) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Related Job Details --}}
                    @if ($job)
                        <div class="card shadow-sm mb-4">
                            <div class="card-header ">
                                <h6 class="mb-0 fw-bold">Job Request Info</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <strong>Category:</strong> {{ optional($job->category)->name ?? '-' }}
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Requested By:</strong> {{ optional($job->user)->name ?? '-' }}
                                    </div>
                                    <div class="col-md-12">
                                        <strong>Description:</strong>
                                        <p class="mt-1">{{ $job->description }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </main>
@endsection
