<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use App\Models\ReferralHistory;
use App\Models\Shop;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function registerReferrer(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string',
                'phone' => 'required|string|unique:referrals,phone',
                'location' => 'required|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($e->validator->errors()->has('phone')) {
                return response()->json(['message' => 'The phone number is already registered.'], 202);
            }
            throw $e; 
        }

        $refCode = strtoupper(substr(md5(time()), 0, 8));

        $referrer = Referral::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'location' => $request->location,
            'ref_code' => $refCode,
        ]);

        return response()->json(['message' => 'Referrer registered', 'ref_code' => $refCode], 201);
    }

    public function checkReferralCode($code)
    {
        $referral = Referral::where('ref_code', $code)->first();
        if ($referral) {
            return response()->json(['valid' => true, 'referrer' => $referral->name]);
        }
        return response()->json(['valid' => false]);
    }

    public function getReferrals()
    {
        return response()->json(
            Referral::with(['referralHistories.shop', 'referralHistories.shop.subscription'])->get()
        );
    }

    public function getUserRefs($referrerId)
    {
        $referrer = Referral::with('referralHistories.shop')->findOrFail($referrerId);
        return response()->json($referrer);
    }

    public function getUserReferralsByPhone($phone)
    {
        $referral = Referral::where('phone', $phone)->first();

        if (!$referral) {
            return response()->json([], 404);
        }

        $referralHistory = $referral->referralHistories()
            ->with(['shop', 'shop.subscription'])
            ->get();

        return response()->json([
            'referral' => $referral,
            'referralHistory' => $referralHistory,
        ]);
    }

    public function checkReferralExists($phone)
    {
        $referral = Referral::where('phone', $phone)->first();
        if ($referral) {
            $referralHistory = $referral->referralHistories()->with(['shop', 'shop.subscription'])->get();

            return response()->json(['valid' => true, 'referrer' => $referral, 'referralHistory' => $referralHistory]);
        }
        return response()->json(['valid' => false]);
    }
}
