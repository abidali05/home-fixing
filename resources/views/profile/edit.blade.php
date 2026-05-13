@extends('layouts.app')

@section('content')
    <div class="main-content position-relative max-height-vh-100 h-100">
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header pb-0">
                            <div class="d-flex align-items-center">
                                <p class="mb-0">Edit Profile</p>

                            </div>
                        </div>
                        <div class="card-body">
                            @if (session('success'))
                                <div class="alert alert-success" role="alert">
                                    {{ session('success') }}
                                </div>
                            @endif
                            @if (session('error'))
                                <div class="alert alert-danger" role="alert">
                                    {{ session('error') }}
                                </div>
                                
                            @endif
                            <form method="POST" action="{{ route('profile.update') }}">
                                @csrf
                                @method('POST')
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-control-label">Full Name</label>
                                        <input class="form-control" type="text" name="name"
                                            value="{{ old('name', Auth::user()->name) }}">
                                            @error('name')
                                            {{ $message }}
                                                
                                            @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-control-label">Email</label>
                                        <input class="form-control" type="email" name="email"
                                            value="{{ old('email', Auth::user()->email) }}">
                                            @error('email')
                                            {{ $message }}
                                            @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-control-label">Phone</label>
                                        <input class="form-control" type="text" name="phone"
                                            value="{{ old('phone', Auth::user()->phone) }}">
                                            @error('phone')
                                            {{ $message }}
                                            @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-control-label">Password</label>
                                        <input class="form-control" type="password" name="password"
                                            placeholder="Leave blank to keep current">
                                            @error('password')
                                            {{ $message }}
                                            @enderror
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-control-label">Address</label>
                                        <input class="form-control" type="text" name="address"
                                            value="{{ old('address', Auth::user()->address) }}">
                                            @error('address')
                                            {{ $message }}
                                            @enderror
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-success btn-sm">Update Profile</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>


            </div>
        </div>
    </div>
@endsection
