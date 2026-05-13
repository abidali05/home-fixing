@extends('layouts.app')

@section('title', 'App Version')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-md-10 col-lg-12">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">App Version Settings</h6>
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

                            @if (session('success'))
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    {{ session('success') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

                            <form action="{{ route('admin.app_versions.save') }}" method="POST">
                                @csrf
                                <div class="mb-3">
                                    <label for="android_version" class="form-label">Android Version</label>
                                    <input type="text" class="form-control" id="android_version" name="android_version"
                                        value="{{ old('android_version', $appVersion->android_version ?? '') }}">
                                </div>
                                <div class="mb-3">
                                    <label for="playstore_link" class="form-label">Play Store Link</label>
                                    <input type="url" class="form-control" id="playstore_link" name="playstore_link"
                                        value="{{ old('playstore_link', $appVersion->playstore_link ?? '') }}">
                                </div>
                                <div class="mb-3">
                                    <label for="ios_version" class="form-label">iOS Version</label>
                                    <input type="text" class="form-control" id="ios_version" name="ios_version"
                                        value="{{ old('ios_version', $appVersion->ios_version ?? '') }}">
                                </div>
                                <div class="mb-3">
                                    <label for="app_store_link" class="form-label">App Store Link</label>
                                    <input type="url" class="form-control" id="app_store_link" name="app_store_link"
                                        value="{{ old('app_store_link', $appVersion->app_store_link ?? '') }}">
                                </div>
                                <button type="submit" class="btn btn-success">
                                    {{ $appVersion ? 'Update' : 'Save' }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
