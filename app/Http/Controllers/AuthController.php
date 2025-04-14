<?php

namespace App\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Str;
use App\Mail\NotificationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            if (User::where('email', $request->email)->exists()) {
                return response()->json(['message' => 'User already exists'], 409);
            }

            $request->validate([
                'first_name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
            ]);
            
            // email_verification_token
            $token = Str::random(60);

            $user = User::create([
                'name' => $request->first_name . ' ' . $request->last_name,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'gender' => $request->gender ?? '',
                'address' => $request->address ?? '',
                'email_verification_token' => $token,
                'bvn' => $request->bvn ?? '',
                'dob' => $request->dob ?? '',
                'password' => Hash::make($request->password),
            ]);

            if (!$user) {
                return response()->json(['message' => 'User could not be created'], 500);
            }

            $wallet = Wallet::create([
                'user_id' => $user->id,
                'balance' => 0,
                'status' => 'active'
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            $notificationController = new EmailController();
            $notificationController->sendSignupEmail($user->id);

            // $user->sendEmailVerificationNotification();

            return response()->json(['access_token' => $token, 'token_type' => 'Bearer', 'user' => $user, 'wallet' => $wallet]);

        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while creating the user'], 500);
        }
    }

    public function login(Request $request)
    {
        try {

            if ($request->user()) {
                return response()->json(['message' => 'User already logged in'], 409);
            }

            $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json(['message' => 'Invalid credentials'], 200);
            }

            // $shop = Shop::where('admin_id', $user->id)->first();
            // if ($shop && $shop->free_trial) {
            //     if ($shop->created_at->addDays(7)->lt(now())) {
            //         $shop->status = 'inactive';
            //         $shop->save();
            //     }
            // }

            $token = $user->createToken('auth_token')->plainTextToken;

            $notificationController = new EmailController();
            $notificationController->sendSigninEmail($user->id);

            return response()->json(['access_token' => $token, 'token_type' => 'Bearer', 'user' => $user]);

        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
            return response()->json(['message' => 'An error occurred while logging in'], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->tokens()->delete();
            return response()->json(['message' => 'Logged out successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while logging out'], 500);
        }
    }

    public function updateUser(Request $request)
    {

        try {

            $user = User::where('id', $request->id)->first();

            if($user){

                DB::beginTransaction();

                $user->gender = $request->gender ?? $user->gender;

                $user->address = $request->address ?? $user->address;

                $user->role = $request->role ?? $user->role;

                // $user->profile = $file ?? $user->profile;

                $user->dob =  $request->dob ?? $user->dob;

                $user->discount = $request->discount ?? $user->discount;

                $user->username = $request->username ?? $user->username;

                // $user->next_of_kin = $request->next_of_kin ?? $user->next_of_kin;

                $user->bvn = $request->bvn ?? $user->bvn;

                $user->nin = $request->nin ?? $user->nin;

                $user->phone = $request->phone ?? $user->phone;

                $user->account_bank = $request->account_bank ?? $user->account_bank;

                $user->account_name = $request->account_name ?? $user->account_name;

                $user->account_number = $request->account_number ?? $user->account_number;

                $user->transfer_code = $request->transfer_code ?? $user->transfer_code;

                $user->customer_id = $request->customer_id ?? $user->customer_id;

                $user->status = 1;

                // dd($user);

                $user->save();

                if($request->updatingBank == 'true'){
                    $notificationController = new EmailController();
                    $notificationController->sendBankAccountEmail($user->id);
                }

                if($user){

                    DB::commit();

                    return $user;
                }else{
                    DB::rollback();

                    return null;

                }
            }
            else{

                    return response()->json(['message' => 'User not found'], 404);

              }


        } catch (\Exception $e) {
           return $e->getMessage();
        }



    }

    public function verifyEmail(Request $request, $token)
    {
        $user = User::where('email_verification_token', $token)->first();

        if (!$user) {

            return view('account.verification-error');
        }

        // Mark the email as verified
        $user->email_verified_at = now();
        $user->email_verification_token = null; // Clear the token
        $user->verified = 1;
        $user->save();

        $notificationController = new EmailController();
        $notificationController->sendWelcomeEmail($user->id);

        return view('account.verification-success');
    }


    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8',
        ]);

        $user = User::where('email', $request->email)
                    ->where('password_reset_token', $request->token)
                    ->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid token or email.'], 400);
        }

        // Update the user's password and clear the token
        $user->password = Hash::make($request->password);
        $user->password_reset_token = null; // clear the token
        $user->save();

        $subject = 'Password Changed Successfully !';
        $messageBody = 'Your password has been changed successfully! If you did not make this change, please contact our support team.';
        $actionUrl = null; 
        $actionText = null;

        Mail::to($user->email)->send(new NotificationMail($subject, $messageBody, $actionUrl, $actionText, $user));

        return redirect('/password/success');
    }

}

