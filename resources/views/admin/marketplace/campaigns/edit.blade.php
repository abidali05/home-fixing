@extends('layouts.app')

@section('title', 'Edit Campaign')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Edit Campaign</h6>
                            <a href="{{ route('marketplace.campaigns.index') }}" class="btn btn-sm btn-secondary">Back</a>
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

                            <form action="{{ route('marketplace.campaigns.update', $campaign->id) }}" method="POST" enctype="multipart/form-data" class="admin-loader-form">
                                @csrf
                                @include('admin.marketplace.campaigns._form')
                                <div class="d-flex justify-content-end mt-4">
                                    <button type="submit" class="btn btn-primary">Update Campaign</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
