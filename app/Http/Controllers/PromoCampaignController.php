<?php

namespace App\Http\Controllers;

use App\Models\PromoCampaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PromoCampaignController extends Controller
{
    public function index()
    {
        $shopId = Auth::user()->shop_id;
        if (!$shopId) {
            return response()->json(['error' => 'Shop not found'], 404);
        } 
        
        return PromoCampaign::withCount('uses')
        ->where('shop_id', $shopId)
        ->get()
        ->map(function($campaign) {
            return [
                ...$campaign->toArray(),
                'unique_users' => $campaign->uses()->distinct('user_id')->count('user_id'),
                'recent_uses' => $campaign->uses()
                    ->with('order')
                    ->latest()
                    ->take(5)
                    ->get()
            ];
        });
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:percentage,fixed,bogo',
            'shop_id' => 'required|exists:shops,id',
            'discount_value' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'valid_days' => 'nullable|array',
            'valid_days.*' => 'integer|between:0,6',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'show_on_menu' => 'boolean',
            'show_as_banner' => 'boolean',
        ]);

        $promo_code = trim($request->input('promo_code'));
        if (!$promo_code) {
            $promo_code = $this->generateUniquePromoCode();
        }

        $usage_limit = $request->input('usage_limit', null); // Optional usage limit
        $times_used = 0; // Default to 0 times used

        $request->merge([
            'promo_code' => $promo_code,
            'usage_limit' => $usage_limit,
            'times_used' => $times_used,
        ]);
        
        $campaign = PromoCampaign::create([
            'shop_id' => $request->shop_id,
            ...$request->all()
        ]);

        return response()->json($campaign, 201);
    }

    private function generateUniquePromoCode()
    {
        do {
            $code = 'P_' . strtoupper(\Str::random(5));
        } while (PromoCampaign::where('promo_code', $code)->exists());

        return $code;
    }

    public function update(Request $request, $id)
    {
        // $this->authorize('update', $promoCampaign);
        $promoCampaign = PromoCampaign::findOrFail($id);

        if (!$promoCampaign) {
            return response()->json(['error' => 'Campaign not found'], 404);
        }

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:percentage,fixed,bogo',
            'discount_value' => 'sometimes|numeric|min:0',
            'description' => 'nullable|string',
            'valid_days' => 'nullable|array',
            'valid_days.*' => 'integer|between:0,6',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'show_on_menu' => 'boolean',
            'show_as_banner' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $promoCampaign->update($request->all());

        return response()->json($promoCampaign);
    }

    public function destroy(PromoCampaign $promoCampaign)
    {
        $promoCampaign->delete();
        return response()->json(null, 204);
    }

    public function show($id)
    {
        $campaign = PromoCampaign::where('id', $id)->first();
        
        if (!$campaign) {
            return response()->json(['error' => 'Campaign not found'], 404);
        }

        $campaign->times_used = $campaign->uses()->count();
        $campaign->unique_users = $campaign->uses()->distinct('user_id')->count('user_id');
        $campaign->usage_limit = $campaign->usage_limit ?? null; 

        return response()->json($campaign);

    }
    public function getActiveCampaigns()
    {
        $shopId = Auth::user()->shop_id;
        if (!$shopId) {
            return response()->json(['error' => 'Shop not found'], 404);
        }

        $activeCampaigns = PromoCampaign::where('shop_id', $shopId)
            ->where('is_active', true)
            ->where(function ($query) {
                $now = now();
                $query->where(function ($q) use ($now) {
                    $q->whereNull('start_date')
                      ->orWhere('start_date', '<=', $now);
                })->where(function ($q) use ($now) {
                    $q->whereNull('end_date')
                      ->orWhere('end_date', '>=', $now);
                });
            })
            ->get();

        return response()->json($activeCampaigns);
    }
    /**
     * Check if a promo code is valid and active.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function isValidPromoCode(Request $request)
    {
        $code = $request->input('code');

        if (!$code) {
            return response()->json([
                'valid' => false,
                'message' => 'Promo code is required'
            ]);
        }
        
        $campaign = PromoCampaign::where('promo_code', $code)
            ->where('is_active', true)
            ->where('shop_id', $request->shopId)
            ->first();
        
        if (!$campaign) {
            return response()->json([
                'valid' => false,
                'message' => 'Promo code not found'
            ]);
        }

        // Check if the campaign is active
        if (!$campaign->isActive()) {
            return response()->json([
                'valid' => false,
                'message' => 'Promo code is not active'
            ]);
        }

        // Check usage limit
        if ($campaign->usage_limit && $campaign->times_used >= $campaign->usage_limit) {
            return response()->json([
                'valid' => false,
                'message' => 'Promo code usage limit reached'
            ]);
        }

        // Prepare discount details
        $discountAmount = (float) $campaign->discount_value;
        $discountType = $campaign->type; // "percentage" or "fixed"
        $discountValue = $discountType === 'percentage'
            ? "{$campaign->discount_value}%"
            : "₦" . number_format($campaign->discount_value, 2);

        // Prepare expiry date (use end_date if set, else null)
        $expiresAt = $campaign->end_date 
            ? \Carbon\Carbon::parse($campaign->end_date)->toIso8601String() 
            : null;

        // Prepare message
        $message = $discountType === 'percentage'
            ? "{$campaign->discount_value}% discount applied"
            : "{$discountValue} discount applied";

        return response()->json([
            'valid' => true,
            'discountAmount' => $discountAmount,
            'discountType' => $discountType,
            'discountValue' => $discountValue,
            'expiresAt' => $expiresAt,
            'message' => $message
        ]);
    }

    public function checkPromoCode(Request $request)
    {
        $code = $request->input('code');
        $campaign = PromoCampaign::where('promo_code', $code)->first();
        if (!$campaign) {
            return response()->json(['valid' => false]);
        }
        return response()->json(['valid' => true]);
    }
    
    public function getCampaignByCode($code)
    {
        $campaign = PromoCampaign::where('promo_code', $code)->first();
        if (!$campaign) {
            return response()->json(['error' => 'Campaign not found'], 404);
        }
        return response()->json($campaign);
    }
    public function applyCampaignToOrder($orderId, $promoCode)
    {
        $campaign = PromoCampaign::where('promo_code', $promoCode)->first();
        if (!$campaign) {
            return response()->json(['error' => 'Promo code not found'], 404);
        }

        $order = Order::find($orderId);
        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        if ($campaign->usage_limit && $campaign->times_used >= $campaign->usage_limit) {
            return response()->json(['error' => 'Promo code usage limit reached'], 400);
        }

        // Apply the campaign to the order
        $order->promo_campaign_id = $campaign->id;
        $order->promo_code = $promoCode;
        $order->save();

        // Increment the times used for the campaign
        $campaign->increment('times_used');

        return response()->json(['message' => 'Promo code applied successfully', 'order' => $order]);
    }
    public function getCampaignsByShop($shopId)
    {
        $campaigns = PromoCampaign::where('shop_id', $shopId)->get();
        return response()->json($campaigns);
    }
    public function getActiveCampaignsByShop($shopId)
    {
        $activeCampaigns = PromoCampaign::where('shop_id', $shopId)
            ->where('is_active', true)
            ->where(function ($query) {
                $now = now();
                $query->where(function ($q) use ($now) {
                    $q->whereNull('start_date')
                      ->orWhere('start_date', '<=', $now);
                })->where(function ($q) use ($now) {
                    $q->whereNull('end_date')
                      ->orWhere('end_date', '>=', $now);
                });
            })
            ->get();

        return response()->json($activeCampaigns);
    }
    public function getCampaignsByType($type)
    {
        $campaigns = PromoCampaign::where('type', $type)->get();
        return response()->json($campaigns);
    }
    public function getCampaignsByDateRange($startDate, $endDate)
    {
        $campaigns = PromoCampaign::whereBetween('start_date', [$startDate, $endDate])
            ->orWhereBetween('end_date', [$startDate, $endDate])
            ->get();
        return response()->json($campaigns);
    }
    public function getCampaignsByTimeRange($startTime, $endTime)
    {
        $campaigns = PromoCampaign::where(function ($query) use ($startTime, $endTime) {
            $query->where('start_time', '>=', $startTime)
                  ->orWhere('end_time', '<=', $endTime);
        })->get();
        return response()->json($campaigns);
    }
    public function getCampaignsByDayOfWeek($dayOfWeek)
    {
        $campaigns = PromoCampaign::whereJsonContains('valid_days', $dayOfWeek)->get();
        return response()->json($campaigns);
    }
    public function getCampaignsByDiscountValue($minValue, $maxValue)
    {
        $campaigns = PromoCampaign::whereBetween('discount_value', [$minValue, $maxValue])->get();
        return response()->json($campaigns);
    }
    public function getCampaignsByUsageLimit($usageLimit)
    {
        $campaigns = PromoCampaign::where('usage_limit', $usageLimit)->get();
        return response()->json($campaigns);
    }
    public function getCampaignsByTimesUsed($timesUsed)
    {
        $campaigns = PromoCampaign::where('times_used', $timesUsed)->get();
        return response()->json($campaigns);
    }
    public function getCampaignsByPromoCode($promoCode)
    {
        $campaign = PromoCampaign::where('promo_code', $promoCode)->first();
        if (!$campaign) {
            return response()->json(['error' => 'Promo code not found'], 404);
        }
        return response()->json($campaign);
    }
}