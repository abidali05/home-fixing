@extends('layouts.guest')
@section('title', 'Forgot Password')

@section('content')
    <main class="main-content mt-0">
        <section>
            <div class="page-header min-vh-100">
                <div class="container">
                    <div class="row">
                        <div class="col-xl-4 col-lg-5 col-md-7 d-flex flex-column mx-lg-0 mx-auto">
                            <div class="card card-plain">
                                <div class="card-header pb-0 text-start">
                                    <h4 class="font-weight-bolder">Forgot Password</h4>
                                    <p class="mb-0 text-sm text-muted">
                                        Enter your email to receive OTP.
                                    </p>
                                </div>

                                <div class="card-body">
                                    @if (session('status'))
                                        <div class="alert alert-success">
                                            {{ session('status') }}
                                        </div>
                                    @endif

                                    @if ($errors->any())

                                        @foreach ($errors->all() as $error)
                                            <p class="text-danger">{{ $error }}</p>
                                        @endforeach

                                </div>
                                @endif
                                @php
                                    $action = route('password.email'); // default

                                    if (session('otp') && !session('otpvalidated')) {
                                        $action = route('password.validate_otp');
                                    } elseif (session('otpvalidated')) {
                                        $action = route('password.store');
                                    }
                                @endphp


                                <form method="POST" action="{{ $action }}">
                                    @csrf

                                    {{-- Email Input --}}
                                    <div class="mb-3 {{ session('otp') ? 'd-none' : '' }}">
                                        <input type="email" name="email" class="form-control form-control-lg"
                                            placeholder="Email" value="{{ old('email') ?? session('email') }}"
                                            @if (!session('otp') && !session('otpvalidated')) required @endif autofocus>
                                        @error('email')
                                            <div class="text-danger mt-2">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- OTP Input --}}
                                    <div class="mb-3 {{ session('otp') && !session('otpvalidated') ? '' : 'd-none' }}">
                                        <input type="number" name="otp" class="form-control form-control-lg"
                                            placeholder="OTP" value="{{ old('otp') }}"
                                            @if (session('otp') && !session('otpvalidated')) required @endif autofocus>
                                        @error('otp')
                                            <div class="text-danger mt-2">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- New Password --}}
                                    <div class="mb-3 {{ session('otpvalidated') ? '' : 'd-none' }}">
                                        <input type="password" name="password" class="form-control form-control-lg"
                                            placeholder="New Password" @if (session('otpvalidated')) required @endif>
                                        @error('password')
                                            <div class="text-danger mt-2">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- Confirm Password --}}
                                    <div class="mb-3 {{ session('otpvalidated') ? '' : 'd-none' }}">
                                        <input type="password" name="password_confirmation"
                                            class="form-control form-control-lg" placeholder="Confirm Password"
                                            @if (session('otpvalidated')) required @endif>
                                        @error('password_confirmation')
                                            <div class="text-danger mt-2">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="text-center">
                                        <button type="submit" class="btn btn-lg btn-primary w-100">
                                            Submit
                                        </button>
                                    </div>
                                </form>

                            </div>
                        </div>
                    </div>

                    <div
                        class="col-6 d-lg-flex d-none h-100 my-auto pe-0 position-absolute top-0 end-0 text-center justify-content-center flex-column">
                        <div class="position-relative bg-gradient-primary h-100 m-3 px-7 border-radius-lg d-flex flex-column justify-content-center overflow-hidden"
                            style="background-image: url('https://raw.githubusercontent.com/creativetimofficial/public-assets/master/argon-dashboard-pro/assets/img/signin-ill.jpg'); background-size: cover;">
                            <span class="mask bg-gradient-primary opacity-6"></span>
                            <h4 class="mt-5 text-white font-weight-bolder position-relative">"Reset and Restart"</h4>
                            <p class="text-white position-relative">Secure your account in a few seconds.</p>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </section>
    </main>
@endsection
