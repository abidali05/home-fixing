@extends('layouts.guest')
<style>
    #sidenav-main {
        display: none !important;
    }
</style>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

@section('title', 'Unauthorized Access')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container py-5">
            <div class="row justify-content-center align-items-center">
                <div class="col-md-8 text-center">
                    <div class="card shadow-sm p-4">
                        <div class="card-body">
                            <h1 class="display-4 text-danger mb-3">
                                <i class="bi bi-shield-lock-fill me-2"></i> 401 Unauthorized
                            </h1>
                            <p class="lead mb-4">You are not authorized to access this page.</p>
                            <a href="{{ route('dashboard') }}" class="btn btn-danger">
                                <i class="bi bi-arrow-left me-1"></i> Go Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
