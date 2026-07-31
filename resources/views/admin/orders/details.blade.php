@extends('layouts.app')

@section('title', 'Order Details')

@section('content')
    @php
        $setting = App\Models\Admin\SystemSettingModel::first();
    @endphp

    <style>
        @media print {
            .sidenav, .navbar, .no-print, .btn, footer, #iconSidenav, .dropdown {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
                margin-top: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }
            .card {
                border: none !important;
                box-shadow: none !important;
            }
            .card-header {
                border-bottom: 2px solid #ddd !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
            }
            body {
                background: #fff !important;
                color: #000 !important;
                font-size: 14px !important;
            }
            .print-receipt-header {
                display: block !important;
                margin-bottom: 30px;
                border-bottom: 3px double #ddd;
                padding-bottom: 15px;
            }
        }
        
        .print-receipt-header {
            display: none;
        }
    </style>

    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            
            {{-- Printable Receipt Header (Hidden on screen) --}}
            <div class="print-receipt-header">
                <div class="row align-items-center mb-4">
                    <div class="col-6">
                        <h4 class="font-weight-bold mb-0" style="color: #4F2396;">{{ optional($setting)->system_name ?? 'Home Fixing' }}</h4>
                        <p class="text-xs text-muted mb-0">Transaction Receipt & Evidence</p>
                    </div>
                    <div class="col-6 text-end">
                        <h4 class="mb-0 font-weight-bold">ORDER RECEIPT</h4>
                        <p class="text-xs text-muted mb-0">Receipt ID: #SRV-{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</p>
                    </div>
                </div>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-12">

                    {{-- Order Details --}}
                    <div class="card shadow-sm mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 text-dark font-weight-bold">Order Details</h5>
                            <div class="no-print">
                                <a href="javascript:void(0);" onclick="window.print();" class="btn btn-sm btn-info me-2" style="background-color: #4F2396 !important; border-color: #4F2396 !important;">
                                    <i class="bi bi-printer me-1"></i> Print Receipt
                                </a>
                                <a href="{{ route('orders.index') }}" class="btn btn-secondary btn-sm">
                                    <i class="bi bi-arrow-left me-1"></i> Back
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <strong>User / Client:</strong> {{ optional($order->user)->name ?? '-' }}
                                </div>
                                <div class="col-md-6">
                                    <strong>Provider / Staff:</strong> {{ optional($order->provider)->name ?? '-' }}
                                </div>
                                <div class="col-md-6">
                                    <strong>Order Date:</strong> {{ \Carbon\Carbon::parse($order->created_at)->format('d M, Y h:i A') }}
                                </div>
                                <div class="col-md-6">
                                    <strong>Price / Amount:</strong> <strong class="text-success">SAR {{ number_format($order->price, 2) }}</strong>
                                </div>
                                <div class="col-md-6">
                                    <strong>Source:</strong> {{ ucfirst($order->source) }}
                                </div>
                                <div class="col-md-6">
                                    <strong>Status:</strong>
                                    <span class="badge bg-{{ $order->status == 'pending' ? 'warning' : ($order->status == 'completed' ? 'success' : 'secondary') }}">
                                        {{ ucfirst($order->status) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Related Job Details --}}
                    @if ($job)
                        <div class="card shadow-sm mb-4">
                            <div class="card-header pb-0">
                                <h6 class="mb-0 fw-bold text-dark">Job Request Info</h6>
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
                                        <p class="mt-1 text-muted">{{ $job->description }}</p>
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
