<?php

namespace App\Http\Controllers;

use App\Services\GooglePlacesService;
use Illuminate\Http\Request;
use App\Models\BusinessClaim;
use App\Models\ReferredBusiness;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

class BusinessDiscoveryController extends Controller
{
    protected $placesService;

    public function __construct(GooglePlacesService $placesService)
    {
        $this->placesService = $placesService;
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'query' => 'required|string',
            'type' => 'sometimes|string',
            'lat' => 'sometimes|numeric',
            'lng' => 'sometimes|numeric',
            'location' => 'sometimes|string', 
            'radius' => 'sometimes|numeric',
        ]);

        $location = null;
        
        // Handle coordinate-based location
        if ($request->has('lat') && $request->has('lng')) {
            $location = [$request->lat, $request->lng];
        }
        elseif ($request->has('location')) {
            $geocoded = $this->geocodeLocation($request->location);
            if ($geocoded) {
                $location = [$geocoded['lat'], $geocoded['lng']];
            }
        }

        $results = $this->placesService->searchBusinesses(
            $validated['query'], 
            $validated['type'] ?? 'restaurant',
            $location,
            $validated['radius'] ?? 5000
        );

        return response()->json($results);
    }

    public function geocodeLocation(string $address)
    {
        $endpoint = 'https://maps.googleapis.com/maps/api/geocode/json';
        
        $response = Http::get($endpoint, [
            'address' => $address,
            'key' => env('GOOGLE_PLACES_API_KEY'),
            'components' => 'country:NG', 
        ]);
        
        if ($response->successful() && !empty($response->json()['results'])) {
            $location = $response->json()['results'][0]['geometry']['location'];
            return ['lat' => $location['lat'], 'lng' => $location['lng']];
        }
        
        return null;
    }


    public function getDetails(string $placeId)
    {
        $details = $this->placesService->getBusinessDetails($placeId);
        
        if (!$details) {
            return response()->json(['error' => 'Business not found'], 404);
        }
        
        return response()->json($details);
    }

    public function claimBusiness(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'place_id' => 'required|string',
            'name' => 'required|string',
            'email' => 'required|email',
            'phone' => 'required|string',
            'address' => 'required|string',
            'user_name' => 'required|string',
            'user_phone' => 'required|string',
            'user_email' => 'required|email',
        ]);
        
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $existingClaim = BusinessClaim::where('place_id', $request->place_id)
            ->where('business_email', $request->email)
            ->where('claimed_by_phone', $request->user_phone)
            ->where('claimed_by_email', $request->user_email)
            ->first();

        if ($existingClaim) {
            return response()->json([
                'message' => 'Claim request submitted successfully',
                'claim_id' => $existingClaim->id,
            ]);
        }
        
        $claim = BusinessClaim::create([
            'place_id' => $request->place_id,
            'business_name' => $request->name,
            'business_email' => $request->email,
            'business_phone' => $request->phone,
            'business_address' => $request->address,
            'claimed_by_name' => $request->user_name,
            'claimed_by_phone' => $request->user_phone,
            'business_logo' => $request->logo ?? null, 
            'claimed_by_email' => $request->user_email,
            'status' => 'pending',
        ]);
        
        return response()->json([
            'message' => 'Claim request submitted successfully',
            'claim_id' => $claim->id,
        ]);
    }

    public function referBusiness(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'place_id' => 'nullable|string',
            'name' => 'required|string',
            'address' => 'required|string',
            'phone' => 'nullable|string',
            'category' => 'required|string',
            'referrer_name' => 'required|string',
            'referrer_email' => 'required|email',
            'referrer_phone' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $referred = ReferredBusiness::create($request->all());

        return response()->json([
            'message' => 'Business referral submitted successfully',
            'data' => $referred
        ]);
    }

    public function approveClaim(Request $request, $claimId)
    {
        $claim = BusinessClaim::findOrFail($claimId);

        // Check if user already exists
        if (User::where('email', $claim->business_email)->exists()) {
            return response()->json(['message' => 'User already exists'], 201);
        }

        DB::beginTransaction();
        try {
            // Optionally, you can generate a random password and send it to the user
            $password = Str::random(10);

            // Create Shop
            $shop = Shop::create([
                'admin_id' => null,
                'shop_name' => $claim->business_name,
                'address' => $claim->business_address,
                'city' => null,
                'state' => null,
                'country' => 'Nigeria',
                'phone_number' => $claim->business_phone,
                'email' => $claim->business_email,
                'description' => null,
                'banner' => $claim->business_logo,
                'status' => 'active'
            ]);

            // Create User (admin)
            $user = User::create([
                'name' => $claim->claimed_by_name,
                'email' => $claim->business_email,
                'phone_number' => $claim->claimed_by_phone,
                'shop_id' => $shop->id,
                'password' => bcrypt($password),
                'role' => 'admin',
            ]);

            // Update shop admin_id
            $shop->admin_id = $user->id;
            $shop->save();

            // Mark claim as approved
            $claim->status = 'approved';
            $claim->save();

            // Create free subscription
            $subscription = new SubscriptionController();
            $subscription->createFreePlan($shop->id, $user->id);

            // Email verification token
            $token = Str::random(60);
            $user->email_verification_token = $token;
            $user->save();

            // Try to send email but don't let it fail the approval
            try {
                // $notificationController = new EmailController();
                // $notificationController->sendSignupEmail($user->id);
            } catch (\Exception $emailException) {
                \Log::error('Failed to send registration email: ' . $emailException->getMessage());
            }

            DB::commit();

            return response()->json([
                'token' => $token,
                'shop' => $shop,
                'user' => $user,
                'password' => $password,
                'message' => 'Claim approved and shop registered successfully.' . 
                            (isset($emailException) ? ' Email verification may not have been sent.' : ''),
                'status' => 201,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Claim approval failed: ' . $e->getMessage());
            return response()->json(['message' => 'Claim approval failed. Please try again.'], 500);
        }
    }
}