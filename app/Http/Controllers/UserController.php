<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Shop;
use App\Models\Referral;
use App\Models\ReferralHistory;
use App\Models\Subscription;
use Illuminate\Support\Facades\Hash;
use Str;

class UserController extends Controller
{
    public function register(Request $request)
    {
        $email = $request->input('email');
        $password = $request->input('password');

        $user = User::where('email', $email)->first();

        if ($user) {

            if (password_verify($password, $user->password)) {
                // Generate a token and log the user in
                $token = $user->createToken('user-token')->plainTextToken;
                return response()->json([
                    'message' => 'Login successful',
                    'token' => $token,
                    'user' => $user,
                    'status' => 200,
                ]);
            } else {
                // Password mismatch
                return response()->json([
                    'message' => 'Invalid credentials',
                    'status' => 401,
                ]);
            }
        } else {
            // If the user does not exist, create a new account
            $newUser = User::create([
                'email' => $email,
                'password' => bcrypt($password),
                'name' => $request->input('name') ?? "Guest",
                'role' => 'user',
            ]);

            if ($newUser) {
                // Generate a token for the new user
                $token = $newUser->createToken('user-token')->plainTextToken;
                return response()->json([
                    'message' => 'Account created successfully',
                    'token' => $token,
                    'user' => $newUser,
                    'status' => 201,
                ]);
            } else {
                // Failed to create the user
                return response()->json([
                    'message' => 'Account creation failed',
                    'status' => 500,
                ]);
            }
        }
    }


    public function registerShop(Request $request) {
        
        if (User::where('email', $request->email)->exists()) {
            return response()->json(['message' => 'User already exists'], 201);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'shop_name' => 'required|string',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'phone' => 'nullable|string',
            'description' => 'nullable|string',
            'banner' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $bannerPath = null;
        if ($request->hasFile('banner')) {
            $banner = $request->file('banner');
            $bannerPath = $banner->store('banners', 'public');
        }

        // Create Shop Information
        $shopData = [
            'admin_id' => null,
            'shop_name' => $validated['shop_name'],
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'country' => 'Nigeria', // Default country
            'phone_number' => $validated['phone'],
            'email' => $validated['email'],
            'description' => $validated['description'] ?? null,
            'banner' => $bannerPath,
            'status' => "active"
        ];

        $shop = Shop::create($shopData);
    
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone_number' => $validated['phone'],
            'shop_id' => $shop->id,
            'password' => bcrypt($validated['password']),
            'role' => 'admin',
        ]);

        if ($request->ref_code) {
            $referrer = Referral::where('ref_code', $request->ref_code)->first();
            ReferralHistory::create([
                'referrer_id' => $referrer->id,
                'shop_id' => $shop->id,
            ]);
        }

        // update shop admin id
        Shop::where('id', $shop->id)->update(['admin_id' => $user->id]);

        $subscription = new SubscriptionController();
        $subscription->createFreePlan($shop->id, $user->id);
    
        $token = Str::random(60); 
    
        $user->email_verification_token = $token;
        $user->save();

        $notificationController = new EmailController();
        $notificationController->sendSignupEmail($user->id);
    
        return response()->json(['token' => $token, 'shop' => $shopData, 'user' => $user]);
    }
    

    // login    
    public function login(Request $request) {
        $validated = $request->validate([
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8',
        ]);
    
        $user = User::where('email', $validated['email'])->first();
    
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 200);
        }

        if ($user->role === 'admin') {
            $shop = Shop::where('admin_id', $user->id)->first();
            $subscription = Subscription::where('shop_id', $shop->id)->first();
            if ($shop && $shop->free_trial) {
                if ($shop->created_at->addDays(7)->lt(now())) {
                    $shop->free_trial = false;
                    $shop->save();
                }
            }
            $shop->plan = $subscription;
            return response()->json(['token' => $user->createToken('user-token')->plainTextToken, 'shop' => $shop, 'user' => $user]);
        }
    
        $token = $user->createToken('user-token')->plainTextToken;
    
        return response()->json(['token' => $token, 'user' => $user]);
    }

    /**
     * Change user password.
     */
    public function changePassword(Request $request)
    {
        // Validate the request input
        $validator = $request->validate([
            'current_password' => 'required|string|min:8',
            'new_password' => 'required|string|min:8',
        ]);

        $user = $request->user(); // Get the currently authenticated user

        // Check if the current password is correct
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 403);
        }

        // Update the password
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Password updated successfully'], 200);
    }

    // change password from token
    public function changePasswordFromToken(Request $request) {
        $user = User::where('email_verification_token', $request->token)->first();
        if (!$user) {
            return response()->json(['message' => 'Invalid token'], 400);
        }
        $user->password = Hash::make($request->password);
        $user->email_verification_token = null;
        $user->save();
        return response()->json(['message' => 'Password updated successfully', 'success' => true, 'user' => $user], 200);
    }
    
}
