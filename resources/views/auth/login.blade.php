@php
     session()->forget(['otp', 'otpvalidated', 'email']);
@endphp

@extends('layouts.guest')
@section('title', 'Login')

@section('content')
    <main class="main-content mt-0">
        <section>
            <div class="page-header min-vh-100">
                <div class="container">
                    <div class="row">
                        <div class="col-xl-4 col-lg-5 col-md-7 d-flex flex-column mx-lg-0 mx-auto">
                            <div class="card card-plain">
                                <div class="card-header pb-0 text-start">
                                    <h4 class="font-weight-bolder">Login</h4>
                                    <p class="mb-0">Enter your email and password</p>
                                </div>

                                <div class="card-body card-header">
                                    <form method="POST" action="{{ route('login') }}">
                                        @csrf

                                        <div class="mb-3">
                                            <input type="email" name="email" class="form-control form-control-lg"
                                                placeholder="Email" required autofocus value="{{ old('email') }}">
                                            @error('email')
                                           <small class="text-danger">{{ $message }}</small>
                                            @enderror
                                        </div>

                                        <div class="mb-3">
                                            <input type="password" name="password" class="form-control form-control-lg"
                                                placeholder="Password" required>
                                        </div>

                                        <div class="text-center">
                                            <button type="submit" class="btn btn-lg btn-primary w-100 mt-2">Login</button>
                                        </div>

                                        @if (Route::has('password.request'))
                                            <div class="text-center mt-4">
                                                <p class="text-sm text-secondary mb-0">
                                                    <a href="{{ route('password.request') }}"
                                                        class="text-primary font-weight-bold">Forgot password?</a>
                                                </p>
                                            </div>
                                        @endif

                                    </form>
                                </div>
                            </div>
                        </div>

                        <div
                            class="col-6 d-lg-flex d-none h-100 my-auto pe-0 position-absolute top-0 end-0 text-center justify-content-center flex-column">
                            <div class="position-relative bg-gradient-primary h-100 m-3 px-7 border-radius-lg d-flex flex-column justify-content-center overflow-hidden"
                                style="background-image: url('https://raw.githubusercontent.com/creativetimofficial/public-assets/master/argon-dashboard-pro/assets/img/signin-ill.jpg'); background-size: cover;">
                                <span class="mask bg-gradient-primary opacity-6"></span>
                                <h5 class="mt-5 text-white font-weight-bolder position-relative">"Fix What Matters. Fast, Trusted Home Services at Your Fingertips."</h5>
                                
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
@endsection
