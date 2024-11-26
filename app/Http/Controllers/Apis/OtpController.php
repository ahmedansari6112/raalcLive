<?php

namespace App\Http\Controllers\Apis;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Models\OTP;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Mail\SendClientOTP;

class OtpController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'status' => 'false',
                'message' => $validator->errors()
            ], 200); // HTTP 400
        }
    
        // Generate OTP
        $otp = rand(1000, 9999);
        $email = $request->email;
    
        // Store OTP in the database
        OTP::create([
            'email' => $email,
            'otp' => $otp,
            'expires_at' => Carbon::now()->addMinutes(1),
        ]);
        
        // Send email with OTP
        if(Mail::to($email)->send(new SendClientOTP($otp))){
            return response()->json(['status' => 'true', 'message' => 'OTP sent successfully.'],200);
        }else{
            return response()->json(['status' => 'true', 'message' => 'OTP not send.'],400);
        }
    }
    
    
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|digits:4',
        ]);
    
        if ($validator->fails()) {
            // Collect all error messages into a single string with line breaks
            $errorMessages = implode("\n", $validator->errors()->all());
            return response()->json([
                'status' => 'false',
                'message' => $errorMessages
            ], 400); // HTTP 400
        }
    
        $otpRecord = OTP::where('email', $request->email)
                        ->where('otp', $request->otp)
                        ->where('is_used', false)
                        ->where('expires_at', '>', Carbon::now())
                        ->first();
    
        if (!$otpRecord) {
            return response()->json([
                'status' => 'false',
                'message' => 'Invalid or expired OTP',
            ], 400); // HTTP 400
        }
    
        // Mark OTP as used
        $otpRecord->update(['is_used' => true]);
    
        return response()->json([
            'status' => 'true',
            'message' => 'OTP verified successfully',
        ], 200); // HTTP 200
    }
    
    
    public function updatePassword(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'new_password' => 'required|min:6|confirmed',
        ]);
    
        if ($validator->fails()) {
            // Collect all error messages into a single string with line breaks
            $errorMessages = implode("\n", $validator->errors()->all());
            return response()->json([
                'status' => 'false',
                'message' => $errorMessages
            ], 400); // HTTP 400 Bad Request
        }
    
        // Find the user by email
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'status' => 'false',
                'message' => 'User not found',
            ], 404); // HTTP 404 Not Found
        }
    
        // Update the user's password
        $user->password = Hash::make($request->new_password);
        $user->save();
    
        return response()->json([
            'status' => 'true',
            'message' => 'Password updated successfully',
        ], 200); // HTTP 200 OK
    }
}
