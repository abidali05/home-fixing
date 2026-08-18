<?php

namespace App\Http\Controllers\Auth;

use App\Models\AdminUsers;
use Illuminate\Http\Request;
use App\Services\TwilioService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class LoginController extends Controller
{
    private $twilio;

    public function __construct(TwilioService $twilio)
    {
        $this->twilio = $twilio;
    }

    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => [
                'required',
                'string',
                'regex:/^\+92\d{10}$/',
                'exists:admin_users,phone'
            ]
        ]);


        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();
            $user = AdminUsers::where('phone', $request->phone)->first();
            if (!$user) {
                return response()->json(['status' => 404, 'message' => 'User not found'], 404);
            }

            $otp = '123456';
            $user->otp = $otp;
            $user->save();
            $this->twilio->sendOtp($request->phone, $otp);
            DB::commit();
            return response()->json(['status' => 200, 'message' => 'OTP sent successfully.'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 500,
                'message' => 'Failed to send OTP',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function verifyOtp(Request $request)
    {
        $result = $this->twilio->verifyOtp($request->phone, $request->code);

        if ($result->status === 'approved') {
            return response()->json(['status' => 200, 'message' => 'OTP verified'], 200);
        }

        return response()->json(['status' => 422, 'message' => 'Invalid OTP'], 422);
    }


    public function login(Request $request)
    {
        // dd($request->all());
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'exists:admin_users,email'],
            'password' => ['required', 'string'],
        ]);

        $user = AdminUsers::where('email', $credentials['email'])->first();

        if (!$user || !Auth::guard('admin')->attempt($credentials)) {
            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ])->onlyInput('email');
        }
        if ($user->status != 'active') {
            return back()->withErrors([
                'email' => 'Your account is not active. Please contact support.',
            ])->onlyInput('email');
        }


        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Login successful');
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Logged out successfully');
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'exists:admin_users,email'],
        ]);

        try {
            DB::beginTransaction();

            $otp = rand(100000, 999999);
            $user = AdminUsers::where('email', $request->email)->first();

            $user->otp = $otp;
            $user->save();

            $body = view('emails.forget_password', ['user' => $user, 'otp' => $otp])->render();

            sendMail($user->name, $user->email, 'HomeFixing', 'Forgot Password', $body);
            session(['otp' => true, 'email' => $user->email]);
            DB::commit();

            return redirect()->back()->with('status', 'OTP sent to your email');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Forgot Password Error: ' . $e->getMessage());

            return redirect()->back()->withErrors([
                'email' => 'Something went wrong. Please try again later.',
            ]);
        }
    }

    public function validateOtp(Request $request)
    {
        // dd($request->all());
        $request->validate([
            'email' => ['required', 'string', 'email', 'exists:admin_users,email'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $user = AdminUsers::where('email', $request->email)->first();
        // dd($user);
        if (!$user || $user->otp !== (int)$request->otp) {
            return back()->withErrors([
                'status' => 'The provided OTP is invalid.',
            ])->onlyInput('email');
        }

        // dd('#otpvalidated');
        session(['otpvalidated' => true]);

        return redirect()->back()->with('status', 'OTP validated successfully');
    }

    public function resetPassword(Request $request)
    {
        // dd($request->all());
        $request->validate([
            'email' => ['required', 'string', 'email', 'exists:admin_users,email'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[a-zA-Z])(?=.*\d).{8,}$/'
            ],
        ]);



        $user = AdminUsers::where('email', $request->email)->first();

        if (!$user) {
            return back()->withErrors([
                'email' => 'The provided email does not match our records.',
            ])->onlyInput('email');
        }

        $user->password = Hash::make($request->password);
        $user->otp = null;
        $user->save();
        session()->forget(['otp', 'otpvalidated', 'email']);
        return redirect()->route('login')->with('status', 'Password reset successfully');
    }
}
